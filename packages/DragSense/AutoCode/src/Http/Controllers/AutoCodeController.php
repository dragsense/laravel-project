<?php

namespace DragSense\AutoCode\Http\Controllers;

use DragSense\AutoCode\Http\Controllers\Controller;
use DragSense\AutoCode\Models\Collection;
use DragSense\AutoCode\Models\Page;
use DragSense\AutoCode\Services\SettingServices;
use DragSense\AutoCode\Services\ThemeServices;
use DragSense\AutoCode\Utils\Utils;
use Exception;
use File;
use Illuminate\Http\Request;
use Log;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

class AutoCodeController extends Controller
{
    protected $settingServices;
    protected $themeServices;
    private $expressionLanguage;

    private  $pattern;

    public function __construct(SettingServices $settingServices, ThemeServices $themeServices)
    {
        $this->settingServices = $settingServices;
        $this->themeServices = $themeServices;

        $this->expressionLanguage = new ExpressionLanguage();
        $this->pattern = '/\{\{[^}]*\}\}/';
    }

    /**
     * Handles the page request based on the incoming URL slug. It fetches the appropriate page or collection
     * from the database, checks for maintenance or error pages, generates the required HTML, CSS, and JS, 
     * and returns the view for rendering. If no page is found, it returns a 404 error page.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\View\View|\Illuminate\Http\Response The response containing the rendered view or a 404 error page.
     * @throws \Exception if an error occurs during the process.
     */
    public function handlePageRequest(Request $request)
    {
        try {
            // Extract dynamic slugs from request path
            $dynamicSlugs = trim($request->getPathInfo(), '/');
            $slugParts = explode('/', $dynamicSlugs);

            // Define projection fields for querying the database
            $projection = ['_id', 'setting', 'slug', 'type', 'scripts'];

            // Fetch settings
            $settings = $this->settingServices->getSettings();
            $page = null;
            $remainingSlug = '';

            // Check if the maintenance page is set and fetch it if applicable
            if (!empty($settings['maintenancePage'])) {
                $page = Page::select($projection)
                    ->where('_id', $settings['maintenancePage']['_id'])
                    ->where('setting->status', 'PUBLIC')
                    ->first();
            } elseif (empty($dynamicSlugs) && !empty($settings['homePage'])) {
                // Check if home page is set and fetch it if no slug provided
                $page = Page::select($projection)
                    ->where('_id', $settings['homePage']['_id'])
                    ->where('setting->status', 'PUBLIC')
                    ->first();
            } else {
                // Check for page by slug
                $page = Page::select($projection)
                    ->where('slug', $dynamicSlugs)
                    ->where('setting->status', 'PUBLIC')
                    ->first();

                // If the page is not found, check in collections
                if (!$page) {
                    foreach ($slugParts as $i => $slugPart) {
                        $currentSlug = implode('/', array_slice($slugParts, 0, $i + 1));

                        $page = Collection::select($projection)
                            ->where('slug', $currentSlug)
                            ->where('setting->status', 'PUBLIC')
                            ->first();

                        if ($page) {
                            $remainingSlug = implode('/', array_slice($slugParts, $i + 1));
                            break;
                        }
                    }
                }
            }

            // If page not found, check for the error page settings
            if (!$page && !empty($settings['errorPage'])) {
                $page = Page::select($projection)
                    ->where('_id', $settings['errorPage']['_id'])
                    ->where('setting->status', 'PUBLIC')
                    ->first();
            }

            // If still no page found, return 404
            if (!$page) {
                return $this->handlePageNotFound();
            }

            // Prepare URLs

            $protocol = request()->getScheme() ?? 'http';
            $host = request()->header('Host');
            $fullhost = "{$protocol}://{$host}";

            // Fetch page data and generate HTML
            $pageResults = $this->fetchPageData($page, $fullhost, $remainingSlug);
            $html = '';

            // Prepare global settings for the view
            $globalSetting = [
                'global' => [
                    'webTitle' => $settings['webTitle'] ?? 'Default Title',
                    'tagLine' => $settings['tagLine'] ?? 'Default Tag Line',
                    'desc' => $settings['desc'] ?? 'Default Description',
                    'author' => $settings['author'] ?? 'Default Author',
                    'images' => $settings['images'] ?? [],
                ],
                'host' => $fullhost
            ];

            // Generate HTML content if the page has elements
            if (isset($pageResults['pages'][$page->_id])) {
                $elements = $pageResults['pages'][$page->_id]['elements'];
                $pageSetting = $pageResults['pages'][$page->_id]['pageSetting'];

                $html = $this->generateHTML(
                    $elements,
                    "0",
                    $pageResults['pages'],
                    $pageSetting,
                    $globalSetting,
                    $fullhost
                );
            }

            // Fetch theme assets
            $options = ['limit' => 10];
            $fonts = $this->themeServices->getFonts([], $options);
            $colors = $this->themeServices->getColors([], $options);
            $variables = $this->themeServices->getVariables([], $options);
            $globalCss = $this->themeServices->getCss();
            $globalJs = $this->themeServices->getJs();

            // Prepare CSS root variables and font styles
            $rootCss = "html { " .
                implode(' ', array_map(fn($font) => "{$font['variable']}: {$font['fontFamily']};", $fonts['data'])) .
                implode(' ', array_map(fn($color) => "{$color['variable']}: {$color['color']};", $colors['data'])) .
                implode(' ', array_map(fn($variable) => "{$variable['variable']}: {$variable['value']};", $variables['data'])) .
                " }";

            $fontCssString = implode('', array_map(function ($font) {
                if ($font['isGoogleFont']) {
                    return $font['src'];
                } elseif (is_array($font['fontSrc']) && count($font['fontSrc']) > 0) {
                    $fontSrcString = implode(',', array_map(fn($src) => "url(\"{$src}\")", $font['fontSrc']));
                    return "<style>
                            @font-face {
                                font-family: \"{$font['fontFamily']}\";
                                src: {$fontSrcString};
                            }
                        </style>";
                }
                return '';
            }, $fonts['data']));

            // Define file paths for custom styles and scripts
            $cssFilePath = base_path('dist/autocode-custom-style.css');
            $jsFilePath = base_path('dist/autocode-custom-index.js');

            // Ensure directories exist before writing files
            File::ensureDirectoryExists(dirname($cssFilePath));
            File::ensureDirectoryExists(dirname($jsFilePath));

            // Write CSS and JS to files
            $pageCss = implode("\n", $pageResults['css']);
            File::put($cssFilePath, "{$rootCss}\n{$globalCss['css']}\n{$pageCss}");
            File::put($jsFilePath, $globalJs['code']);

            // Return the view with all necessary data
            return view('autocode-app', [
                'type' => $page->type ?? 'page',
                '_id' => $page->_id,
                'globalSetting' => $globalSetting,
                'settings' => $settings,
                'pageSetting' => $page['setting'],
                'pageScripts' => $page['scripts'],
                'remainingSlug' => $remainingSlug,
                'fontCssString' => $fontCssString,
                'html' => $html,
                'autocodeApiPrefix' => "/" . env("AUTOCODE_API_PREFIX", 'autocode-api')
            ]);
        } catch (Exception $e) {
            // Log error and return 404 page
            Log::error("Error in handlePageRequest: {$e->getMessage()}");
            return $this->handlePageNotFound();
        }
    }

    /**
     * Handles the scenario when a requested page is not found.
     * Returns a simple HTML response with a 404 status code.
     *
     * @return \Illuminate\Http\Response The response containing a 404 error page.
     */
    function handlePageNotFound()
    {
        return response()->make(
            "<!DOCTYPE html>
        <html>
            <head>
                <title>404</title>
            </head>
            <body>
                <div id='root'>Page not found.</div>
            </body>
        </html>",
            404
        )->header('Content-Type', 'text/html');
    }




    /**
     * Generates HTML based on the provided elements, page settings, global settings, and states.
     * The function recursively processes elements and their child nodes, applying conditions
     * and attributes, and handles special cases for tags like `img` and `input`.
     *
     * @param array $elements The array of elements to generate HTML from.
     * @param string $nodeId The ID of the current node being processed.
     * @param array $pageResults The result set containing page elements and states.
     * @param array $pageSetting The settings specific to the current page.
     * @param array $globalSetting The global settings for the page.
     * @param string $host The host URL.
     * @param array $states The current states for the page.
     * @param array $props The current props for the page.
     * @return string The generated HTML string.
     */
    function generateHTML(&$elements, $nodeId, &$pageResults, &$pageSetting, &$globalSetting, &$host, &$states = [], &$props = [])
    {
        if (!isset($elements[$nodeId])) {
            Log::error("Element with ID {$nodeId} not found in elements.");
            return '';
        }

        $element = $elements[$nodeId];
        if (empty($element['tagName'])) {
            Log::error("Element with ID {$nodeId} does not have a tagName.");
            return '';
        }

        $conditions = $element['conditions'] ?? [];
        $mapValue = [];
        $mapIndex = 0;

        if (!$this->calculateConditions($conditions, $states, $props, $pageSetting, $globalSetting, $mapValue, $mapIndex)) {
            return ''; // Skip rendering this element if conditions are not met
        }

        // Initialize HTML as an empty string
        $html = '';

        // Handle 'map' elements
        if (isset($element['map'])) {
            $mapItems = $this->getStatesValueType($element['map'], $states, $props, $host);

            foreach ($mapItems as $mapIndex => $mapValue) {
                $html .= $this->renderElement($element, $elements, $pageResults, $pageSetting, $globalSetting, $states, $props, $mapValue, $mapIndex);
            }
        } else {
            $html .= $this->renderElement($element, $elements, $pageResults, $pageSetting, $globalSetting, $states, $props, $host);
        }

        return $html;
    }

    /**
     * Renders a single HTML element, applying attributes, classes, and handling special cases
     * for certain tags. It also processes child nodes recursively.
     *
     * @param array $element The element to be rendered.
     * @param array $elements The array of all elements for the page.
     * @param array $pageResults The result set containing page elements and states.
     * @param array $pageSetting The settings specific to the current page.
     * @param array $globalSetting The global settings for the page.
     * @param array $states The current states for the page.
     * @param array $props The current states for the page.
     * @param array $mapValue The current map value for iterated elements.
     * @param int $mapIndex The index for the current map iteration.
     * @return string The generated HTML string for the element.
     */
    private function renderElement(
        $element,
        &$elements,
        &$pageResults,
        &$pageSetting,
        &$globalSetting,
        &$states,
        &$props,
        $host,
        &$mapValue = [],
        &$mapIndex = 0
    ) {
        $tagName = $element['tagName'] ?? '';

        if (!$tagName) {
            Log::error('Element tagName is missing.');
            return '';
        }

        $state = $element['state'] ?? ['type' => '', 'key' => ''];

        $nodeValue = $this->withFallBackNodeValue($element, $element['nodeValue'] ?? '', $globalSetting['global'], $host);

        $nodeValue = isset($element['isStateActive']) && $element['isStateActive']
        ? $this->replacePlaceholders($nodeValue, $states, $props, $pageSetting, $globalSetting, $mapValue, $mapIndex)
        : $nodeValue;

        $value = $this->getValue($state, $states, $props, $pageSetting, $globalSetting, $mapValue, $mapIndex, $nodeValue);
        
        $html = "<{$tagName}";

        // Generate attributes
        $attributes = $this->calculateAttributes(
            $element['attributes'] ?? [],
            $states,
            $props,
            $pageSetting,
            $globalSetting,
            $mapValue,
            $mapIndex
        );



        // Handle dynamic and static classes
        $dynamicClasses = isset($element['classes']) ? $this->reduceClassNames($element['classes'], $states, $props, $pageSetting, $globalSetting, $mapValue, $mapIndex) : '';
        $allClasses = trim(($element['className'] ?? '') . ' ' . $dynamicClasses);

        if (!empty($allClasses)) {
            $html .= " class=\"{$allClasses}\"";
        }

        // Handle special cases for img and input tags
        if (in_array($tagName, ['img', 'input']) && $value) {
            $html .= ($tagName === 'img') ? " src=\"" . html_entity_decode($value['value'] ?? $value) . "\"" : " value=\"" . html_entity_decode($value['value'] ?? $value) . "\"";
        }

        if (in_array($tagName, ['video']) && $value) {
            $poster = $this->createPoster($value, $tagName, $host);
            // Adding poster for video
            if ($tagName === 'video') {
                $html .= " poster=\"" . html_entity_decode($poster['src']) . "\"";
            }
        }

        $html .= " controls";

        foreach ($attributes as $key => $attributeValue) {
            if ($attributeValue !== null) {
                $html .= " {$key}=\"" . html_entity_decode($attributeValue) . "\"";
            }
        }

        $html .= '>';

        // Add content inside the tag if it's not an img or input
        if (!in_array($tagName, haystack: ['img', 'input', 'video', 'audio']) && $value) {
            $html .= '<span>' . html_entity_decode($value['value'] ?? $value) . '</span>';
        }

        if (in_array($tagName, ['video', 'audio']) && $value) {
            $poster = $this->createPoster($value, $tagName, $host);
            $srcs = is_array($value['srcs']) ? $value['srcs'] : [];
            $sources = $this->createSources($srcs, $poster, $tagName, $host);

            // Adding sources for video/audio
            foreach ($sources as $source) {

                $html .= "<source src=\"" . html_entity_decode($source['src']) . "\" type=\"" . html_entity_decode($source['type']) . "\" />";
            }

            $html .= $tagName === 'video' ? 'Your browser does not support the video tag.' : 'Your browser does not support the audio tag.';
        }

        // Recursively process child nodes
        foreach ($element['childNodes'] ?? [] as $childNodeId) {
            $html .= $this->processChildNode($childNodeId, $element, $elements, $pageResults, $pageSetting, $globalSetting, $host, $states, $props);
        }

        $html .= "</{$tagName}>";

        return $html;
    }

    /**
     * Processes a child node by either recursively generating its HTML or handling special cases like forms and components.
     * If the child node ID is "0", it handles the root node of a nested structure. 
     * It also handles specific scenarios where the child node is associated with a page and needs to render form or component tags.
     *
     * @param string $childNodeId The ID of the child node to process.
     * @param array $element The current element containing the child node.
     * @param array $elements The full set of elements in the document.
     * @param array $pageResults The results from fetching the pages, including their elements and states.
     * @param array $pageSetting The current page's settings.
     * @param array $globalSetting Global settings that apply across the entire document.
     * @param string $host The host URL or identifier.
     * @param array $states The state data used for rendering dynamic content.
     * 
     * @return string The HTML string generated for the child node.
     */
    private function processChildNode($childNodeId, $element, &$elements, &$pageResults, &$pageSetting, &$globalSetting, &$host, &$states, &$props)
    {
        $html = '';

        if ($childNodeId === "0") {
            $elementUid = $element['_uid'];
            $pageElements = $elements[$elementUid] ?? null;
            $pageId = $pageElements && isset($pageElements["page"]) ? $pageElements["page"]['_id'] : -1;
            $innerElements = $pageResults[$pageId]['elements'] ?? [];
            $newstates = $pageResults[$pageId]['states'] ?? [];
            $newProps = $states;

            $html .= $this->generateHTML($innerElements, "0", $pageResults, $pageSetting, $globalSetting, $host, $newstates, $newProps);
        } else {
            $page = $elements[$childNodeId]['page'] ?? null;
            if ($page) {
                $pageId = $page['_id'];
                $pageType = $page['pageType'];

                $html .= $pageType === 'form'
                    ? "<span data-name='form'><form action='" . config('autocode.prefix') . "/v1/forms/submit/{$pageId}' method='POST'>"
                    : "<span data-name='component'>";
            }

            $html .= $this->generateHTML($elements, $childNodeId, $pageResults, $pageSetting, $globalSetting, $host, $states, $props);

            if ($page) {
                $html .= $pageType === 'form' ? '</form></span>' : '</span>';
            }
        }

        return $html;
    }

    /**
     * Retrieves the value type based on the provided element's type and key.
     * The function can return values from states, props, or a default value.
     *
     * @param array $elem The element containing the type and key to retrieve.
     * @param array $states The current states for the page.
     * @param array $props The properties for the page.
     * @param string $host The host URL.
     * @param array $value The default value to return if no type is matched.
     * @return mixed The retrieved value based on the element type.
     */
    function getStatesValueType($elem, &$states, &$props, $host, $value = [])
    {
        $type = $elem['type'] ?? '';
        $key = $elem['key'] ?? '';

        return match ($type) {
            'STATES' => Utils::getStateStates($key, $states, $host),
            'PROPS' => Utils::getStateStates($key, $props, $host),
            default => $value,
        };
    }


    /**
     * Retrieves the value based on the provided element's type and key.
     * The function can return values from global settings, states, page settings, or a default value.
     *
     * @param array $elem The element containing the type and key to retrieve.
     * @param array $states The current states for the page.
     * @param array $props The current states for the page.
     * @param array $settings The settings for the current page.
     * @param array $globalSetting The global settings for the page.
     * @param array $mapValue The current map value for iterated elements.
     * @param int $mapIndex The index for the current map iteration.
     * @param mixed $defaultValue The default value to return if no type is matched.
     * @return mixed The retrieved value based on the element type.
     */
    function getValue(
        $elem,
        &$states,
        &$props,
        &$settings,
        &$globalSetting,
        &$mapValue,
        &$mapIndex,
        $defaultValue = ''
    ) {
        $type = $elem['type'] ?? '';
        $key = $elem['key'] ?? '';

        return match ($type) {
            'GLOBAL' => Utils::getGlobalValue($key, $globalSetting),
            'STATES' => Utils::getStateValue($key, $states, $settings['host'] ?? null),
            'PROPS' => Utils::getStateValue($key, $props, $settings['host'] ?? null),
            'PAGE' => Utils::getPageValue($key, $settings, $settings['host'] ?? null),
            'FORMSTATES' => $states[$key[0]][$key[1]] ?? "''",
            'MAPVALUE' => $mapValue,
            'MAPINDEX' => $mapIndex,
            default => $defaultValue,
        };
    }

    /**
     * This function is used to evaluate the condition expression.
     * 
     * @param string $expression - The condition expression.
     * @param string $v - The state value.
     * @param string $keyValue - The key value.
     * @return bool The result of the condition evaluation.
     */
    private function evaluateCondition(string $expression, string $v, string $keyValue): bool
    {
        // Replace placeholders in the expression
        $expression = str_replace(['[state]', '[key]'], [$v, $keyValue], $expression);

        try {
            // Evaluate the modified expression
            return (bool) $this->expressionLanguage->evaluate($expression);
        } catch (SyntaxError $e) {
            // Handle syntax errors in the expression
            // You can log the error or show an error message
            throw new \InvalidArgumentException("Invalid expression: " . $e->getMessage());
        } catch (Exception $e) {
            // Handle any other exceptions
            throw new \RuntimeException("Error evaluating expression: " . $e->getMessage());
        }

    }



    /**
     * This function is used to calculate the conditions for rendering the component.
     * 
     * @param array $conditions - The conditions array.
     * @return bool The result of the conditions evaluation.
     */
    private function calculateConditions(
        array $conditions,
        &$states,
        &$props,
        &$settings,
        &$globalSetting,
        &$mapValue,
        &$mapIndex
    ): bool {
        if (empty($conditions))
            return true;
        return array_reduce($conditions, function ($prev, $condition) use ($states, $props, $settings, $globalSetting, $mapValue, $mapIndex) {
            $value = $this->getValue(
                $condition,
                $states,
                $props,
                $settings,
                $globalSetting,
                $mapValue,
                $mapIndex
            );
            $keyValue = isset($value['value']) ? $value['value'] : $value;
            $stateValue =
                isset($condition['valueState']) ?
                $this->getValue(
                    $condition['valueState'],
                    $states,
                    $props,
                    $settings,
                    $globalSetting,
                    $mapValue,
                    $mapIndex,
                    ''
                )
                : $condition['value'];
            $v = isset($stateValue['value']) ? $stateValue['value'] : $stateValue;
            $cond = true;

            if (isset($condition['value'])) {
                $cond = $this->evaluateCondition($condition['value'], $v, $keyValue);
            }

            return $condition['relation'] === 'AND' ? $prev && $cond : $prev || $cond;
        }, true);
    }

    /**
     * This function is used to calculate the attribute value.
     * 
     * @param array $attribute - The attribute array.
     * @return mixed The calculated attribute value.
     */
    private function calculateAttributeValue(
        array $attribute,
        &$states,
        &$props,
        &$settings,
        &$globalSetting,
        &$mapValue,
        &$mapIndex
    ) {
        $attributeValue = null;

        if ($attribute['key'] === 'style') {
            $attributeValue = $this->calculateAttributes(
                $attribute['attributes'],
                $states,
                $props,
                $settings,
                $globalSetting,
                $mapValue,
                $mapIndex
            );
        } else {
            $stateValue =
                isset($attribute['valueState']) ?
                $this->getValue(
                    $attribute['valueState'],
                    $states,
                    $props,
                    $settings,
                    $globalSetting,
                    $mapValue,
                    $mapIndex,
                    $attribute['value']
                )
                : $attribute['value'];
            $value = $stateValue['value'] ?? $stateValue;

            if (is_string($attribute['value']) && !empty($attribute['value'])) {
                $value = str_replace('[state]', $value, $attribute['value']);

                try {
                    eval ("\$attributeValue = $value;");
                } catch (Exception $e) {
                    // Handle the error according to your application's requirements.
                    error_log("Invalid expression: " . $e->getMessage());
                }
            } else {
                $attributeValue = $value;
            }
        }

        $conditions = $attribute['conditions'] ?? [];
        $cond = $this->calculateConditions(
            $conditions,
            $states,
            $props,
            $settings,
            $globalSetting,
            $mapValue,
            $mapIndex
        );

        return $cond ? $attributeValue : null;
    }

    /**
     * This function is used to calculate the attributes of the component.
     * 
     * @param array $attributes - The attributes array.
     * @return array The calculated attributes array.
     */
    private function calculateAttributes(
        array $attributes,
        &$states,
        &$props,
        &$settings,
        &$globalSetting,
        &$mapValue,
        &$mapIndex
    ): array {
        if (empty($attributes))
            return [];

        return array_reduce($attributes, function ($a, $attribute) use ($states, $props, $settings, $globalSetting, $mapValue, $mapIndex) {

            $attributeValue = $this->calculateAttributeValue(
                $attribute,
                $states,
                $props,
                $settings,
                $globalSetting,
                $mapValue,
                $mapIndex
            );
            return array_merge($a, [$attribute['key'] => $attributeValue]);
        }, []);
    }

    /**
     * Calculate class names based on conditions and state value.
     *
     * @param array $cls The class details containing conditions and state value.
     * @return string The calculated class name or an empty string if conditions are not met.
     */
    function calculateClassNames(
        $cls,
        &$states,
        &$props,
        &$settings,
        &$globalSetting,
        &$mapValue,
        &$mapIndex
    ) {
        if (!isset($cls['conditions']))
            return '';

        $conditions = $cls['conditions'];
        $cond = $this->calculateConditions(
            $conditions,
            $states,
            $props,
            $settings,
            $globalSetting,
            $mapValue,
            $mapIndex
        );
        $classNames = $this->getValue(
            $cls['stateValue'],
            $states,
            $props,
            $settings,
            $globalSetting,
            $mapValue,
            $mapIndex,
            $cls['name']
        );

        if (is_string($cls['name'])) {
            $classNames = preg_replace('/\[.*?\]/', $classNames['value'] ?? $classNames, $cls['name']);
        }

        return $cond ? $classNames : '';
    }

    /**
     * Reduce an array of class details into a single string of class names.
     *
     * @param array $classes The array of class details.
     * @return string The concatenated class names.
     */
    function reduceClassNames(
        $classes,
        &$states,
        &$props,
        &$settings,
        &$globalSetting,
        &$mapValue,
        &$mapIndex
    ) {
        return array_reduce($classes, function ($carry, $cls) use ($states, $props, $settings, $globalSetting, $mapValue, $mapIndex) {
            $classValue = $this->calculateClassNames(
                $cls,
                $states,
                $props,
                $settings,
                $globalSetting,
                $mapValue,
                $mapIndex
            );
            return $carry . " " . $classValue;
        }, "");
    }

    function createPoster($value, $tag, $host)
    {
        if (!empty($value['value'])) {
            return [
                'src' => $host . $value['value'],
                'alt' => !empty($value['label']) ? $value['label'] : $tag
            ];
        } else {
            return [
                'src' => $value['value'],
                'alt' => $tag
            ];
        }
    }

    function createSources($srcs, $poster, $tag, $host)
    {
        $sources = [];

        foreach ($srcs as $index => $media) {
            $sources[] = [
                'key' => $index,
                'src' => $host . $media['src'],
                'type' => $media['mimetype']
            ];
        }

        if (count($srcs) <= 0 && $tag === 'audio') {
            $sources = [
                [
                    'key' => 'default',
                    'src' => $poster['src'],
                    'type' => 'audio/mp3'
                ]
            ];
        }

        return $sources;
    }


    public function getFallbackPoster(&$settings)
    {
        $defaultImage = './images/default/default-poster.png';
        if (isset($settings['images']['placeholder']['src'])) {
            return $settings['images']['placeholder']['src'];
        }
        return $defaultImage;
    }

    // Method to process node values with fallback
    public function withFallBackNodeValue($node, $nodeValue, &$settings, $host)
    {
        switch ($node['tagName']) {
            case 'img':
                return [
                    'value' => !empty($node['src']) ? $host . $node['src']['src'] : $this->getFallbackPoster($settings),
                    'label' => $node['src']['alt'] ?? ''
                ];

            case 'video':
                return [
                    'value' => !empty($node['src']) ? $node['src']['src'] : $this->getFallbackPoster($settings),
                    'label' => $node['src']['alt'] ?? '',
                    'srcs' => $node['srcs'] ?? []
                ];

            case 'audio':
                return [
                    'value' => '',
                    'srcs' => $node['srcs'] ?? []
                ];

            case 'input':
            case 'textarea':
            case 'option':
                return $nodeValue ?? '';

            default:
                return $nodeValue;
        }
    }




    // Function to replace placeholders in the string with their corresponding values
    function replacePlaceholders(&$inputString, &$states, &$props, &$setting, &$globalSetting, &$mapValue, &$mapIndex)
    {
       

        $resultString = $inputString;
        try {
            while (preg_match($this->pattern, $resultString, $match)) {
                $matchedText = $match[0];
                $keysString = substr($matchedText, 2, -2); // Remove the '{{' and '}}'
                $keys = explode("/", $keysString);
                $modifiedKey = $keys;
                array_shift($modifiedKey); // Shift the first element

                // Create element array with type and key
                $elem = [
                    'type' => $keys[0],
                    'key' => $modifiedKey
                ];

                // Get value (you need to define how this function works)
                $value = $this->getValue($elem, $states, $props, $setting, $globalSetting, $mapValue, $mapIndex);
                $value = isset($value['value']) ? $value['value'] : ($value ?: "");

                $replacementValue = $value;
                $resultString = str_replace($matchedText, $replacementValue, $resultString);
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
        return $resultString;
    }


    /**
     * Fetches the data needed to render the specified page or collection.
     *
     * @param Page|Collection $page The page or collection to fetch data for.
     * @param string $host The current host URL.
     * @param string $remainingSlug Additional slug information, if any.
     * @return array The data for the page or collection.
     */
    private function fetchPageData($page, $host, $remainingSlug)
    {
        // Implement fetching page data logic similar to Node.js
        return $this->settingServices->getPageData($page->_id, $page->type, $host, true, $remainingSlug);
    }

}
