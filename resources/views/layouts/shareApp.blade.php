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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <link rel="stylesheet" href="{{ asset('custom-css/documents.css') }}">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/trix/1.1.1/trix.css">

</head>

<body>


    @include('documents.previewDocument')
    @include('documents.uploads.addUrl')
    @include('documents.uploads.addFolder')
    @include('documents.uploads.requestDocument')
    @include('documents.uploads.shareDocument')

    {{-- <div id="renderSidebarHtmlId">
        @include('layouts.sidebar')
    </div> --}}

    <div id="loadingOverlay">
        <div class="spinner-border" role="status"></div>
    </div>

    <div class="container-fluid">

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
    <script src="{{ asset('custom-js/documents.js') }}"></script>

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

    function previewDocumentImageFile() {
        var Extension = $('.previewFileExtension').val();
        var Path = $('.previewFilePath').val(); // Add missing "=" here
        previewCourseFile(Extension, Path);
    }

    function addUrlModal() {
        $('#uploadModal').modal('show');
    }
</script>


</html>
