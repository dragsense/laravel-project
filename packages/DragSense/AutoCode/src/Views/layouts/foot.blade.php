@section('autocode-foot')

@php
    // Assigning variables with isset checks to ensure array keys exist
    $scriptId = isset($_id) ? "ac-data-script-{$_id}" : 'ac-data-script';
    $type = isset($type) ? $type : 'page';
    $_id = isset($_id) ? $_id : '';
    $globalSetting = isset($globalSetting) ? $globalSetting : [];
    $remainingSlug = isset($remainingSlug) ? $remainingSlug : '';
    $footerScripts = isset($settings['scripts']['footer']) ? $settings['scripts']['footer'] : '';
    $pageFooterScripts = isset($pageScripts['footer']) ? $pageScripts['footer'] : '';

@endphp

<script id="{{ $scriptId }}">
    window.__INITIAL_DATA__ = {!! json_encode([
        'type' => $type,
        '_id' => $_id,
        'setting' => $globalSetting,
        'remainingSlug' => $remainingSlug,
    ]) !!};
</script>
<script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
<script src="/autocode/autocode-custom-index.js"></script>
{!! $footerScripts !!}
{!! $pageFooterScripts !!}
@yield('other-scripts')
<script>
    window.react = window.React;
    window.AUTOCODE_API_PREFIX = "<?php echo $autocodeApiPrefix; ?>";
</script>
<script src="/autocode/autocode-client.js"></script> 

@endsection
