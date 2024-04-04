<div class="header">
    <div class="header-logo">
        <a href="{{ route('home') }}">
            <svg class="site-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                <path
                    d="M512 256a15 15 0 00-7.1-12.8l-52-32 52-32.5a15 15 0 000-25.4L264 2.3c-4.8-3-11-3-15.9 0L7 153.3a15 15 0 000 25.4L58.9 211 7.1 243.3a15 15 0 000 25.4L58.8 301 7.1 333.3a15 15 0 000 25.4l241 151a15 15 0 0015.9 0l241-151a15 15 0 00-.1-25.5l-52-32 52-32.5A15 15 0 00512 256zM43.3 166L256 32.7 468.7 166 256 298.3 43.3 166zM468.6 346L256 479.3 43.3 346l43.9-27.4L248 418.7a15 15 0 0015.8 0L424.4 319l44.2 27.2zM256 388.3L43.3 256l43.9-27.4L248 328.7a15 15 0 0015.8 0L424.4 229l44.1 27.2L256 388.3z" />
            </svg></a>
        <span class="site-title">Document</span>
    </div>

    <div class="header-search">
        @if (!Route::is('home'))
            <button class="button-menu"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 385 385">
                    <path
                        d="M12 120.3h361a12 12 0 000-24H12a12 12 0 000 24zM373 180.5H12a12 12 0 000 24h361a12 12 0 000-24zM373 264.7H132.2a12 12 0 000 24H373a12 12 0 000-24z" />
                </svg></button>
            <div class="d-flex">
                <a class="btn btn-info" href="{{ route('documents.index') }}">
                    Documents <span class="sr-only">(current)</span>
                </a>
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Configuration
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item" href="#">Settings</a>
                        <a class="dropdown-item" href="{{ route('workspaces.create') }}">Workspaces</a>
                        <a class="dropdown-item" href="{{ route('tags.index') }}">Tags</a>
                    </div>
                </div>
            </div>
        @endif

    </div>

    <div class="dropdown">

        <div class="dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown"
            aria-haspopup="true" aria-expanded="false">
            {{-- <h4 style="font-size: 13px" class="text-white mb-0">{{ Auth::user()?->name }}</h4> --}}
            <small style="font-size: 16px" class="text-white">{{ Auth::user()?->name }}</small>
            <x-avatar width="30" height="30" />
        </div>

        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
            <a class="dropdown-item" href="{{ route('workspaces.create') }}"> <span class="fas fa-user"></span>
                Profile</a>
            <a class="dropdown-item tooltip-wrapper" href="{{ route('logout') }}" data-toggle="tooltip"
                data-placement="top" title="" data-original-title="Logout"
                onclick="event.preventDefault();
                document.getElementById('logout-form').submit();">
                <span class="fas fa-sign-out"> </span> Logout
            </a>
        </div>
    </div>

    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
        @csrf
    </form>

</div>

<style>
    .header-search button,
    .header-search a {
        background-color: transparent;
        border: none;
        color: white;
        outline: none;
        /* Remove focus outline */
    }

    .header-search button:hover,
    .header-search button:focus,
    .header-search button:active,
    .header-search a:hover,
    .header-search a:focus,
    .header-search a:active {
        background-color: transparent;
        color: white;
    }

    .header-search button:active,
    .header-search a:active {
        transform: translateY(1px);
        /* Adjust the button/link position on click */
    }


    .dropdown-menu a,
    .dropdown-menu a:hover {
        color: #333;
        border-radius: 0%;
    }
</style>
