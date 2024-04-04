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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <link rel="stylesheet" href="{{ asset('custom-css/documents12.css') }}">

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
        background-color: #333;
        color: #fff;
        box-sizing: border-box;
        box-shadow: 0 0 2rem 0 rgb(0 0 0 / 5%);
        overflow: hidden;
        transition: width 0.5s ease;
    }

    .container-parent.nav-closed .sidebar,
    .container-parent.nav-closed .header-logo {
        width: 0;
    }

    .header {
        height: 50px;
        background: #6a0dad;
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
        /* margin-left: 210px; */
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

<body>

    @auth

        <div id="loadingOverlay">
            <div class="spinner-border" role="status"></div>
        </div>
        @include('documents.previewDocument')

        <div class="container-parent {{ !Route::is('documents.index') ? 'nav-closed' : '' }} ">

            {{-- Header Section --}}
            @if (!isset($shareDocument))
                @include('layouts.header')
            @endif

            <div class="main">

                {{-- Sidebar Section --}}
                <div id="renderSidebarHtmlId">
                    @include('layouts.sidebar')
                </div>

                {{-- Content Section --}}
                <div class="page-content" style="display: none">
                    @if (!isset($shareDocument) && !Route::is('home'))
                        @include('layouts.navbar-search')
                    @endif
                    @yield('content')
                </div>
            </div>
        </div>
    @else
        <div class="main">
            {{-- Content Section --}}
            <div class="page-content" style="display: none">
                @yield('content')
            </div>
        </div>
    @endauth


    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <script src="{{ asset('custom-js/documents1001.js') }}"></script>

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


    function sendEmail(formId) {
        // Get the CSRF token
        var csrfToken = $('meta[name="csrf-token"]').attr('content');

        // Get the form data
        var formData = new FormData($('#' + formId)[0]);

        // Append additional form data
        formData.append('_token', csrfToken); // Append CSRF token
        formData.append('title', $('#documentTitle').val()); // Append title
        formData.append('folder_id', localStorage.getItem('selectedFolderId')); // Append folder_id
        formData.append('document_id', localStorage.getItem('selectedDocumentId')); // Append document_id

        // Send AJAX request
        $.ajax({
            url: '{{ route('send.email') }}', // Replace 'send.email' with your actual route name
            type: 'POST',
            data: formData,
            processData: false, // Prevent jQuery from automatically processing the data
            contentType: false, // Prevent jQuery from automatically setting the content type
            headers: {
                'X-CSRF-TOKEN': csrfToken // Pass the CSRF token in the headers
            },
            success: function(response) {
                // Handle success response
                console.log('Email sent successfully!');
                $('#renderDocumentCommentHtml').html(response.html);
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

    if (menuButton) {
        menuButton.addEventListener("click", function() {
            container.classList.toggle("nav-closed");
        });
    }


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

<script>
    function showFilters() {
        $('.custom-dropdown').css('display', 'flex');
    }


    function copyUrl() {
        // Select the text inside the input field
        var sharedUrl = document.getElementById("sharedUrlId");
        sharedUrl.select();
        sharedUrl.setSelectionRange(0, 99999); // For mobile devices

        // Copy the selected text to the clipboard
        document.execCommand("copy");

        // Deselect the input field
        sharedUrl.blur();

        // Display a message indicating that the URL has been copied
        // alert("URL copied to clipboard: " + sharedUrl.value);
    }

    // Generate a random token
    function generateRandomToken() {
        var timestamp = new Date().getTime().toString(16); // Current timestamp in hexadecimal
        var randomChars = Math.random().toString(36).substring(2, 10); // Random characters
        return timestamp + '_' + randomChars; // Combine timestamp and random characters with underscore
    }

    function openModal(modal) {
        var $modal = $('#' + modal);

        // Check if the modal exists
        if ($modal.length === 0) {
            console.error('Modal with ID ' + modal + ' not found.');
            return;
        }

        // Clear error messages and invalid feedback
        $modal.find('.error-message').html('');
        $modal.find('.invalid-feedback').remove();
        $modal.find('.is-invalid').removeClass('is-invalid');

        // Reset the form
        var form = $modal.find('#FolderCreateForm')[0];
        if (form) {
            form.reset();
        } else {
            console.error('Form not found within modal.');
        }

        // Clear category HTML
        $modal.find('#renderFolderCategoryHtml').empty();

        // Show the modal
        $modal.modal('show');
    }


    function closeModal(modal) {
        $('#' + modal).modal('hide');
        $('.error-message').html('');
    }

    function validation(xhr, $form) {
        if (xhr.status === 422) {
            var errors = xhr.responseJSON.errors;

            // Remove existing error messages and classes
            $form.find('.invalid-feedback').remove();
            $form.find('.is-invalid').removeClass('is-invalid');
            // Loop through errors and display them
            for (var key in errors) {
                var $input = $form.find('[name="' + key + '"]');
                $input.addClass('is-invalid'); // Add is-invalid class to input
                // $input.closest('.form-group').append('<div class="invalid-feedback">' + errors[key][0] + '</div>');
                $form.find('.error-message').html('<div class="p-2 bg-danger text-white">' + errors[key][0] +
                    '</div>');
            }

        } else {
            $form.find('.error-message').html('<div class="p-2 bg-danger text-white">' + xhr +
                '</div>');
        }
    }


    function saveForm(route, formId, successCallback) {
        var form = document.getElementById(formId);
        var $form = $('#' + formId);
        var formData = new FormData(form);

        $.ajax({
            url: route,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (typeof successCallback === 'function') {
                    successCallback(response);
                }
            },
            error: function(xhr, status, error) {
                validation(xhr, $form);
            }
        });
    }
</script>

</html>
