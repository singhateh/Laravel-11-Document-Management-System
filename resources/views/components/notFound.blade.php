@props(['message' => 'No document found', 'size' => '60'])
<div class="col-md-12 mb-3 documentContentClass">
    <div class="no-document-found">
        <img src="{{ asset('img/empty-document.png') }}" alt="No Document Found Image">
        <p>{{ $message }}</p>
    </div>
</div>


@once
    <style>
        .no-document-found {
            text-align: center;
            padding: 20px;
            margin: auto;
            background-color: whitesmoke;
            width: {{ $size }}vw;
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
@endonce
