<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta content="" name="description" />
    <meta content="" name="author" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <link rel="stylesheet" href="{{ asset('custom-css/documents.css') }}">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/trix/1.1.1/trix.css">

</head>

<style>
    /* Custom Modal Styles */
    .custom-modal .modal-content {
        border-radius: 0%;
    }

    .custom-header {
        text-align: center;
        font-size: 20px;
        margin-bottom: 5px;
    }

    .custom-header h5 {
        margin-left: 10px;
    }

    .custom-close {
        position: absolute;
        top: 0;
        right: 0;
        cursor: pointer;
        background-color: red;
        padding: 5px;
        border: none;
        border-bottom-left-radius: 10px;
        color: white;
    }

    .form-group {
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        flex: 1%;
    }

    .custom-label {
        flex: 0.5;
        font-size: 12px;
        font-weight: bold;
        margin-top: 3px;
    }

    .custom-input,
    .custom-select,
    .custom-input-lg {
        padding: 8px;
        /* Adjust padding as needed */
        border: none;
        border-bottom: 1px solid #b0aeae;
        border-radius: 0;
        width: 100%;
        outline: none;
        box-shadow: none;
        height: 34px;
        /* Set height to 34px */
        line-height: 1.5;
        /* Set line-height for text visibility */
        font-size: 14px;
        overflow: hidden;
    }

    .custom-input-lg {
        font-size: 20px;
        font-weight: 600;
    }

    .custom-select {
        /* Remove default appearance */
        background-color: transparent;
        /* Make background transparent */
        background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23333"><path d="M7 10l5 5 5-5H7z"/><path d="M0 0h24v24H0z" fill="none"/></svg>');
        /* Add custom arrow icon */
        background-repeat: no-repeat;
        background-position: right 8px center;
    }

    .custom-input:focus,
    .custom-select:focus,
    .custom-input-lg:focus {
        border: none;
        outline: none;
        box-shadow: none;
        border-bottom: 2px solid #6d6969;
    }

    input:not(:empty),
    select:not(:empty),
    textarea:not(:empty) {
        border-bottom: 2px solid #6d6969;
    }

    .custom-checkbox {
        margin-bottom: 20px;
    }

    .custom-check-label {
        margin-left: 10px;
        margin-top: -12px;
    }

    .custom-footer {
        text-align: left;
    }

    .custom-button {
        padding: 5px 10px;
        background-color: #4CAF50;
        color: white;
        border: none;
        border-radius: 0px;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    .custom-button-close {
        padding: 5px 10px;
        background-color: #f0f0f0;
        color: black;
        border: none;
        border-radius: 0px;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    .custom-button:hover {
        background-color: #45a049;
    }
</style>


<body>

    @if (Route::is('documents.index'))
        <div id="renderSidebarHtmlId">
            @include('layouts.sidebar')
        </div>
    @else
        <style>
            .content {
                margin-left: 0px !important;
                padding: 0px !important;
            }

            .content .navbar-fixed {
                width: 98.5% !important;

            }

            .content .navbar-serach {
                margin-top: 2.7rem !important;
            }
        </style>
    @endif

    <div id="loadingOverlay">
        <div class="spinner-border" role="status"></div>
    </div>

    <div class="container-fluid1">
        <div class="content" style="display: none">
            @include('layouts.navbar')
            @include('layouts.navbar-search')

            @yield('content')
        </div>
    </div>

    {{-- <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script> --}}
    {{-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script> --}}
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


</html>
