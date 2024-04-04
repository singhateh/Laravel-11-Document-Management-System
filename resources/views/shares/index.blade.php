@extends('layouts.app', ['shareDocument' => $shareDocument])

@section('content')

    @if ($shareDocument->hasExpired())
        @include('shares.expired')
    @elseif($shareDocument->isPublic())
        @forelse ($shareDocument->sharesBySlug($shareDocument->slug)->get() as $share)
            @if ($shareDocument->slug === 'document')
                @include('shares.singleDocument')
            @else
                @include('shares.documents')
            @endif
        @empty
            <x-notFound />
        @endforelse
    @else
        @include('shares.no-permission')
    @endif

    @include('shares.preview')

    <style>
        .no-document-found {
            text-align: center;
            padding: 20px;
            margin: auto;
            background-color: whitesmoke;
            width: 60vw;
        }

        .no-document-found img {
            width: 200px;
            /* Adjust size as needed */
            height: auto;
            /* Maintain aspect ratio */
            margin-bottom: 20px;
        }

        .no-document-found p {
            font-size: 18px;
            color: #333;
            /* Adjust color as needed */
        }
    </style>

    <style>
        .documentContentClass {
            margin-top: 5rem;
        }

        .card {
            position: relative;
            border: none !important;
            margin-bottom: 3rem;
        }

        .card-body {
            background-color: #333;
        }

        .card-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            border: none;
            overflow: hidden;
            width: calc(100% + 17px);
            overflow-x: hidden;
            overflow-Y: hidden;
            cursor: cell;
        }

        .card-overlay object {
            overflow: hidden !important;
        }

        object {
            overflow: hidden !important;
            zoom: -10 !important;
            object-fit: contain !important;
        }

        .card:hover .card-overlay {
            opacity: 1;
        }

        .card-overlay-content {
            color: #fff;
        }

        .card-body {
            padding: 0;
        }

        .iframeContainer {
            width: 100%;
            height: 100%;
            border: none;
            overflow: hidden;
            width: calc(100% + 17px);
            overflow-x: hidden;
            overflow-Y: hidden;
            object-fit: fill;

        }

        .iframeContainer object {
            width: 100%;
            height: 100%;
            border: none;
            overflow: hidden;
            width: calc(100% + 17px);
            overflow-x: hidden;
            overflow-Y: hidden;
            margin-bottom: 2rem;
        }
    </style>

@endsection
