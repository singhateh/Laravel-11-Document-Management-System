@props(['width' => 50, 'height' => 50])

<a href="#" class="circle">
    <img height="{{ $width }}" width="{{ $height }}" src="{{ asset('img/profile.jpg') }}"
        alt="{{ Auth::user()->name }}">
</a>

@once
    <!-- Include CSS using once() method -->
    <style>
        /* Your CSS styles here */
        ::-moz-selection {
            background: rgba(0, 0, 0, 0.1);
        }

        ::selection {
            background: rgba(0, 0, 0, 0.1);
        }

        /* Circle Avatar Styles */

        .circle {
            /* Remove line-height */
            line-height: 0;
            /* Display inline-block */
            /* display: block; */
            margin: 5px;
            border: 1px solid rgba(200, 200, 200, 0.4);
            border-radius: 50%;
            /* box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.4); */
            transition: linear 0.25s;
            /* Swap width and height */
            /* width: 10px;
                        height: 10px; */
        }

        .circle img {
            border-radius: 50%;
            /* relative value for adjustable image size */
        }

        .circle:hover {
            /* transition: ease-out 0.2s;
                        border: 1px solid rgba(0, 0, 0, 0.2);
                        -webkit-transition: ease-out 0.2s; */
        }

        a.circle {
            color: transparent;
        }

        /* IE fix: removes blue border */
    </style>
@endonce
