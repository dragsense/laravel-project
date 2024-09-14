@section('autocode-head')

@php
    // Checking and assigning values with isset to avoid potential errors
    $pageTitle = isset($pageSetting['title']) ? $pageSetting['title'] : (isset($settings['webTitle']) ? $settings['webTitle'] : 'Page');
    $tagLine = isset($settings['tagLine']) ? $settings['tagLine'] : null;
    $pageDescription = isset($pageSetting['desc']) ? $pageSetting['desc'] : (isset($settings['desc']) ? $settings['desc'] : '');
    $author = isset($settings['author']) ? $settings['author'] : '';
    $keywords = isset($settings['keywords']) ? $settings['keywords'] : '';
    $faviconSrc = isset($settings['images']['favicon']['src']) ? $settings['images']['favicon']['src'] : '/favicon.ico';
    $faviconType = isset($settings['images']['favicon']['mimetype']) ? $settings['images']['favicon']['mimetype'] : 'image/x-icon';
    $language = isset($settings['lang']) ? $settings['lang'] : '';
    $customScripts = isset($settings['scripts']['head']) ? $settings['scripts']['head'] : '';
    $pageHeadScripts = isset($pageScripts['head']) ? $pageScripts['head'] : '';
@endphp

<title>{{ $pageTitle }}{{ $tagLine ? " - $tagLine" : '' }}</title>
<meta name="description" content="{{ $pageDescription }}">
<meta name="author" content="{{ $author }}">
<meta name="keywords" content="{{ $keywords }}">
<link rel="icon" href="{{ $faviconSrc }}" type="{{ $faviconType }}">
<meta http-equiv="Content-Language" content="{{ $language }}">
<link rel="stylesheet" type="text/css" href="/autocode/autocode-custom-style.css">

{!! $fontCssString !!}
{!! $customScripts !!}
{!! $pageHeadScripts !!}
@endsection
