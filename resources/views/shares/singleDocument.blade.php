<div class="previewSingleDocument">
    @if ($share->extension === 'pdf')
        <object data="{{ asset($share->file_path) }}" type="application/pdf" style1="width: 100%; height: 100%;"></object>
    @elseif(in_array($share->extension, getVideoExtensions()))
        <video controls src="{{ asset($share->file_path) }}" style="width: 100%; height: 80%;" alt="Document"></video>
    @elseif(in_array($share->extension, getImageExtensions()))
        <img src="{{ asset($share->file_path) }}" style="width: 100%; height: 100%;" alt="Document">
    @elseif($share->extension === 'youtube')
        <div id="displayYoutubeVideo"></div>
    @endif
</div>


<style>
    .previewSingleDocument object,
    .previewSingleDocument img,
    .previewSingleDocument video {
        width: 100% !important;
        height: 100vh !important;
        padding: 0;
        margin: 0;
        left: 0;
        right: 0;
        top: 0;
        background-origin: padding-box;
        object-fit: contain;
    }

    .previewSingleDocument img {
        object-fit: contain;
    }

    .page-content {
        padding: 0%;
    }
</style>

{{-- <script>
    var videoId = extractYouTubeVideoIds(@json(asset($share->file_path)));
    const embedUrl = 'https://www.youtube.com/embed/' + videoId + '?rel=0';
    $('#displayYoutubeVideo').html('<iframe src="' + embedUrl + '"></iframe>');

    // Function to extract YouTube video ID from URL
    function extractYouTubeVideoIds(url) {
        const match = url.match(
            /(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/
        );
        return match ? match[1] : null;
    }
</script> --}}
