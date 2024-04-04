<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <link rel="stylesheet" href="https://cdn.lineicons.com/2.0/LineIcons.css">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Vadodara:wght@300;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('custom-css/documents.css') }}">

</head>

<body>

    <style>
        * {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Hind Vadodara", -apple-system, BlinkMacSystemFont, Segoe UI,
                Helvetica Neue, Arial, sans-serif;
        }

        .container-parent {
            display: flex;
            height: 100vh;
            width: 100vw;
            flex-wrap: wrap;
            overflow: hidden;
        }

        .main {
            height: calc(100% - 50px);
            display: flex;
            flex: 1;
        }

        .sidebar {
            height: 100%;
            width: 220px;
            box-sizing: border-box;
            box-shadow: 0 0 2rem 0 rgb(0 0 0 / 5%);
            overflow: hidden;
            transition: width 0.5s ease;
        }

        .container-parent.nav-closed .sidebar,
        .container-parent.nav-closed .header-logo {
            width: 0;
        }

        .sidebar ul {
            display: flex;
            flex-direction: column;
            padding: 5px;
        }

        .sidebar ul li {
            display: flex;
            align-items: center;
        }

        .sidebar ul li a {
            color: #000;
            text-decoration: none;
            display: flex;
            align-items: center;
            width: 100%;
            padding: 10px;
            white-space: nowrap;
        }

        .sidebar ul li a.active,
        .sidebar ul li a:hover {
            background: #e8ecef;
        }

        .sidebar ul li span {
            margin-left: 16px;
            font-size: 16px;
            font-weight: 100;
        }

        .sidebar ul li i {
            font-size: 18px;
            color: #111;
            font-weight: normal;
        }

        .header {
            height: 50px;
            background: #303f9f;
            width: 100%;
            display: flex;
            align-items: center;
            flex-basis: 100%;
        }

        .sidebar ul li a.active i {
            color: #303f9e;
        }

        .site-logo {
            height: 32px;
            width: 32px;
            min-height: 32px;
            min-width: 32px;
            margin: 0 18px 0 15px;
        }

        .site-logo path {
            fill: #fff;
        }

        .site-title {
            color: #fff;
            font-size: 24px;
            letter-spacing: 1px;
            font-weight: 400;
        }

        .page-content {
            padding: 10px 20px;
            box-sizing: border-box;
            width: 100%;
            flex: 1;
        }

        .page-content h1 {
            font-size: 20px;
            font-weight: 400;
            color: #333;
        }

        .header-search {
            height: 100%;
            align-items: center;
            display: flex;
            padding: 0 20px;
            flex: 1;
        }

        .header-search .button-menu {
            width: 28px;
            height: 28px;
            margin-right: 15px;
            background: none;
            border: 0;
            cursor: pointer;
        }

        .header-logo {
            display: flex;
            align-items: center;
            width: 220px;
            overflow: hidden;
            transition: width 0.5s ease;
        }

        .header-search input[type="search"] {
            height: 100%;
            width: 300px;
            padding: 10px 20px;
            box-sizing: border-box;
            font-size: 14px;
            font-weight: 100;
            background: none;
            border: none;
            color: #fff;
        }

        .header-search input[type="search"]:focus {
            outline: none;
        }

        .header-search input[type="search"]::placeholder {
            color: #ccc;
        }

        .header-search .button-menu:focus {
            outline: none;
            border: none;
        }

        .header-search .button-menu svg path {
            fill: #fff;
        }

        @media screen and (max-width: 991px) {
            .page-content {
                width: 100vw;
            }
        }

        @media screen and (max-width: 767px) {
            .header-logo {
                display: none;
            }
        }
    </style>

    <div class="container-parent">
        <div class="header">
            <div class="header-logo">
                <svg class="site-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                    <path
                        d="M512 256a15 15 0 00-7.1-12.8l-52-32 52-32.5a15 15 0 000-25.4L264 2.3c-4.8-3-11-3-15.9 0L7 153.3a15 15 0 000 25.4L58.9 211 7.1 243.3a15 15 0 000 25.4L58.8 301 7.1 333.3a15 15 0 000 25.4l241 151a15 15 0 0015.9 0l241-151a15 15 0 00-.1-25.5l-52-32 52-32.5A15 15 0 00512 256zM43.3 166L256 32.7 468.7 166 256 298.3 43.3 166zM468.6 346L256 479.3 43.3 346l43.9-27.4L248 418.7a15 15 0 0015.8 0L424.4 319l44.2 27.2zM256 388.3L43.3 256l43.9-27.4L248 328.7a15 15 0 0015.8 0L424.4 229l44.1 27.2L256 388.3z" />
                </svg>
                <span class="site-title">Layerz</span>
            </div>
            <div class="header-search">
                <button class="button-menu"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 385 385">
                        <path
                            d="M12 120.3h361a12 12 0 000-24H12a12 12 0 000 24zM373 180.5H12a12 12 0 000 24h361a12 12 0 000-24zM373 264.7H132.2a12 12 0 000 24H373a12 12 0 000-24z" />
                    </svg></button>
                <input type="search" placeholder="Search Documentation..." />
            </div>
        </div>
        <div class="main">
            {{-- <div class="sidebar">
                <ul>
                    <li><a href="#" class="active"><i class="lni lni-home"></i><span>Dashboard</span></a></li>
                    <li><a href="#"><i class="lni lni-text-format"></i><span>Form Elements</span></a></li>
                    <li><a href="#"><i class="lni lni-bar-chart"></i><span>Charts</span></a></li>
                    <li><a href="#"><i class="lni lni-grid"></i><span>Grid System</span></a></li>
                    <li><a href="#"><i class="lni lni-bullhorn"></i><span>Notifications</span></a></li>
                    <li><a href="#"><i class="lni lni-support"></i><span>Help & Support</span></a></li>
                </ul>
            </div> --}}
            <div id="renderSidebarHtmlId">
                @include('layouts.sidebar')
            </div>
            <div class="page-content">
                <div class="row">
                    <div class="col-md-9 " id="documentContent">
                        <div id="renderDocumentContentHtml">

                        </div>
                    </div>

                    <div class="col-md-3 pl-0" id="documentProperty">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="document-properties">
                                    <div class="card">
                                        <div class="card-header">
                                            <input type="hidden" name="" id=""
                                                class="previewFileExtension">
                                            <input type="hidden" name="" id="" class="previewFilePath">
                                            <img src="{{ asset('img/folder.png') }}" alt=""
                                                style="cursor: zoom-in" onclick="previewDocumentImageFile(this)"
                                                id="imageInput">
                                            <i class="icon far fa-comments" onclick="addComment()"></i>
                                        </div>
                                        <div class="card-body folderProperty">
                                            <div id="renderFolderInfoHtml">
                                            </div>
                                        </div>
                                        @include('documents.info')
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-7" id="documentCommentSection" style="display: none">
                                <div id="renderDocumentCommentHtml"></div>
                            </div>
                        </div>
                    </div>
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


    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <script src="{{ asset('custom-js/documents1.js') }}"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

</body>

<style>
    .custom-audio {
        width: 100%;
        /* max-width: 400px; */
        /* Adjust the maximum width as needed */
        /* background-color: #fff; */
        /* border-radius: 10px; */
        /* padding: 20px; */
        /* box-shadow: 0 0 20px rgba(0, 0, 0, 0.3); */
        background-image: url('{{ asset('img/bg-audio.jpg') }}');

    }

    .custom-audio::-webkit-media-controls-panel {
        background-color: transparent;
        /* Make the default controls transparent */
    }
</style>



<script>
    function closeOverlay() {
        $('#previewModal').modal('hide');
    }

    function sendEmail() {
        // Get the CSRF token
        var csrfToken = $('meta[name="csrf-token"]').attr('content');

        // Get the form data
        var formData = {
            title: $('#documentTitle').val(),
            content: $('#messageInput').val(),
            user_email: $('#selectedUsersId').val(),
            folder_id: localStorage.getItem('selectedFolderId'),
            document_id: localStorage.getItem('selectedDocumentId')
        };

        // Send AJAX request
        $.ajax({
            url: '{{ route('send.email') }}', // Replace 'send.email' with your actual route name
            type: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': csrfToken // Pass the CSRF token in the headers
            },
            success: function(response) {
                // Handle success response
                console.log('Email sent successfully!');
                $('#renderDocumentCommentHtml').html(response.html)
            },
            error: function(xhr, status, error) {
                // Handle error response
                console.error('Error sending email:', error);
            }
        });
    }

    function uploadFiles() {
        // Create file input element and trigger file selection
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.multiple = true; // Allow multiple files to be selected
        fileInput.style.display = 'none';
        fileInput.addEventListener('change', function() {
            const files = fileInput.files;
            if (files.length > 0) {
                uploadToServer(files, 'files');
            }
        });
        document.body.appendChild(fileInput);
        fileInput.click();
    }

    function uploadFolder() {
        // Create file input element and trigger file selection
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.multiple = true; // Allow multiple files to be selected
        fileInput.webkitdirectory = true; // Allow selection of directories
        fileInput.style.display = 'none';
        fileInput.addEventListener('change', function() {
            const files = fileInput.files;
            if (files.length > 0) {
                // You can now handle the selected files or folders here
                // For example, you can upload them to the server
                uploadToServer(files, 'folder');
            }
        });
        document.body.appendChild(fileInput);
        fileInput.click();
    }

    function uploadToServer(files, type) {
        // Get the CSRF token from the meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Get folder_id and document_id from local storage
        const folderId = localStorage.getItem('selectedFolderId');
        const documentId = localStorage.getItem('selectedDocumentId');

        // Create a FormData object to append files, token, folder_id, and document_id
        const formData = new FormData();
        formData.append('_token', csrfToken); // Append CSRF token
        formData.append('folder_id', folderId); // Append folder_id
        formData.append('document_id', documentId); // Append document_id
        formData.append('type', type); // Append document_id
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]); // Append each file
        }

        // Perform AJAX request to upload files to the server
        $.ajax({
            url: '/upload', // Replace with your server endpoint
            type: 'POST',
            data: formData, // Pass the FormData object containing files, token, folder_id, and document_id
            processData: false,
            contentType: false,
            success: function(response) {
                fetchFiles(response.url, 'folder');
            },
            error: function(xhr, status, error) {
                console.error('Error uploading files:', error);
            }
        });
    }

    // Function to toggle subfolders
    function toggleSubfolders(button) {
        var subfolders = button.nextElementSibling;
        subfolders.style.display = subfolders.style.display === 'none' ? 'block' : 'none';

        // Store the state in local storage
        var folderId = button.parentNode.dataset.folderId;
        var subfoldersOpen = JSON.parse(localStorage.getItem('subfoldersOpen')) || {};
        subfoldersOpen[folderId] = subfolders.style.display === 'block';
        localStorage.setItem('subfoldersOpen', JSON.stringify(subfoldersOpen));
    }

    // Function to select subfolder
    function selectSubfolder(subfolderId) {
        // Store the state in local storage
        localStorage.setItem('selectedSubfolder', subfolderId);
    }

    function previewDocumentImageFile(element) {
        var url = element.dataset.preview;
        var Extension = $('.previewFileExtension').val();
        var Path = $('.previewFilePath').val(); // Add missing "=" here
        previewCourseFile(Extension, url);
        // alert(url)
    }

    function addUrlModal() {
        $('#uploadModal').modal('show');
    }
</script>


<script>
    let menuButton = document.querySelector(".button-menu");
    let container = document.querySelector(".container-parent");
    let pageContent = document.querySelector(".page-content");
    let responsiveBreakpoint = 991;

    if (window.innerWidth <= responsiveBreakpoint) {
        container.classList.add("nav-closed");
    }

    menuButton.addEventListener("click", function() {
        container.classList.toggle("nav-closed");
    });

    pageContent.addEventListener("click", function() {
        if (window.innerWidth <= responsiveBreakpoint) {
            container.classList.add("nav-closed");
        }
    });


    window.addEventListener("resize", function() {
        if (window.innerWidth > responsiveBreakpoint) {
            container.classList.remove("nav-closed");
        }
    });
</script>

</html>
