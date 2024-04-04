@if ($share->documents->isNotEmpty())
    <style>
        .card-body {
            height: 300px;
            overflow: hidden;
        }

        .card-body object {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        .container-fluid {
            height: 95%;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .btn-primary {
            border: none;
            border-radius: 0%;
            background-color: gainsboro;
            color: #333
        }

        .btn-primary:hover {
            background-color: gainsboro;
            color: #333
        }
    </style>

    <h1><i class="fa fa-folder"></i> {!! $shareDocument->name ? $shareDocument->name : $share->name !!}</h1>

    <div class="container-fluid mt-2">

        <div class="row">
            @foreach ($share->documents as $document)
                <div class="col-md-3 mb-2">
                    <div class="card iframeContainer" style="height: 300px; overflow: hidden;">
                        @if ($document->extension === 'pdf')
                            <object data="{{ asset($document->file_path) }}#toolbar=0" type="application/pdf"
                                style1="width: 100%; height: 100%;"></object>
                        @elseif($share->extension === 'youtube')
                            {{-- <div id="displayYoutubeVideo"></div> --}}
                            <iframe src="{{ asset($document->file_path) }}" frameborder="0"></iframe>
                        @elseif(in_array($document->extension, getVideoExtensions()))
                            <video src="{{ asset($document->file_path) }}" style="width: 100%; height: 100%;"
                                alt="Document"></video>
                        @elseif(in_array($document->extension, getImageExtensions()))
                            <img src="{{ asset($document->file_path) }}" style="width: 100%; height: 100%;"
                                alt="Document">
                        @endif
                        <div class="card-overlay">
                            <div class="card-overlay-content">
                                <button type="button" class="btn btn-primary"
                                    onclick="previewCourseFile('{{ $document->extension }}', '{{ asset($document->file_path) }}')">Preview</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@else
    <x-notFound message="No document in folder" />

@endif
