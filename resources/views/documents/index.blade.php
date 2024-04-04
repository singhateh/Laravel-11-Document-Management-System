@extends('layouts.app')


@section('content')
    <div class="errorMessage"></div>

    <div class="row">
        <div class="col-md-9 " id="documentContent">
            <div id="renderDocumentContentHtml">
                @if ($documents->isEmpty())
                    <x-notFound />
                @endif
            </div>
        </div>

        <div class="col-md-3 pl-0" id="documentProperty">

            <div class="row">
                <div class="col-md-12">

                    @include('documents.info')

                </div>
                <div class="col-md-7" id="documentCommentSection" style="display: none">
                    <div id="renderDocumentCommentHtml"></div>
                </div>
            </div>

        </div>
    </div>


    <style>
        #documentContent {
            max-height: 80vh;
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* Hide scrollbar track */
        ::-webkit-scrollbar-track {
            background: transparent;
        }

        /* scrollbar itself */
        ::-webkit-scrollbar {
            width: 8px;
            /* width of the scrollbar */
        }

        /* Handle when mouse is over the scrollbar */
        ::-webkit-scrollbar-thumb {
            background-color: #ccc4c4;
            /* color of the scrollbar handle */
            border-radius: 2px;
            /* border radius of the scrollbar handle */
        }

        /* Handle when scrollbar is being dragged */
        ::-webkit-scrollbar-thumb:hover {
            background-color: #ccc4c4;
            /* darker color when hovered */
        }
    </style>

    @include('documents.uploads.addUrl')
    @include('documents.uploads.uploadFolder')
    @include('documents.uploads.requestDocument')
    @include('documents.uploads.shareDocument')
@endsection
