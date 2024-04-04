<nav class="navbar navbar-fixed navbar-expand-lg">
    <div class="container">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse d-flex justify-content-between" id="navbar">
            <ul class="navbar-nav mr-auto1 gap-2">
                <li class="nav-item active">
                    <a class="btn btn-info" href="{{ route('documents.index') }}">
                        Documents <span class="sr-only">(current)</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <a class="btn btn-info" href="#" onclick="uploadFiles()">
                        Document <span class="sr-only">(current)</span>
                    </a>
                </li>
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Configuration
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item" href="#">Settings</a>
                        <a class="dropdown-item" href="{{ route('workspaces.create') }}">Workspaces</a>
                        <a class="dropdown-item" href="#">Tags</a>
                    </div>
                </div>
            </ul>
            <div class="search-container">
                <x-avatar width="35" height="35" />
            </div>
        </div>
    </div>
</nav>


<style>
    #navbar .btn {
        background: transparent;
        color: white;
        padding: 3px;
        border: 0px;
    }

    .content .navbar-fixed {
        position: fixed;
        top: 0;
        width: 80.9%;
        z-index: 1000;
        background-color: blueviolet;
    }

    .content .navbar-fixed {
        margin: 0rem;
        padding: 0;
    }
</style>

<script>
    function showFilters() {
        $('.custom-dropdown').css('display', 'flex');
    }

    function shareDocument() {
        var selectedFolder = localStorage.getItem('selectedFolderId');
        var selectedDocument = localStorage.getItem('selectedDocumentId');

        var baseUrl = window.location.origin; // Retrieve the base URL
        var shareUrl;
        if (selectedDocument == '' && selectedFolder) {
            // Generate URL for sharing folder with unique token
            var token = generateRandomToken();
            shareUrl = baseUrl + '/folder/share/' + selectedFolder + '/' + token;
            $('#sharedId').val(selectedFolder);
            $('#sharedTokenId').val(token);
            $('#sharedSlugId').val('folder');
        } else if (selectedDocument) {
            // Generate URL for sharing document with unique token
            var token = generateRandomToken();
            shareUrl = baseUrl + '/document/share/' + selectedDocument + '/' + token;
            $('#sharedId').val(selectedDocument);
            $('#sharedTokenId').val(token);
            $('#sharedSlugId').val('document');
        } else {
            // Handle case when neither folder nor document is selected
            console.error();
            $('#requestDocumentModalBodyId').html(
                '<strong class="mx-auto text-danger">No folder or document selected for sharing.</strong>')
            return;
        }

        $('#sharedUrlId').val(shareUrl);
        $('#selectedDocumentFile').html('this one is nice ')
        // Log the generated share URL
        console.log('Share URL:', shareUrl);
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


    // Function to generate a random token
    function generateRandomToken() {
        // Generate a unique random string using PHP's uniqid function
        return '<?php echo uniqid(); ?>';
    }


    function goToNewRoute(route) {
        $.ajax({
            url: route,
            type: 'GET',
            success: function(response) {
                $('#content').html(response);
            },
            error: function(xhr, status, error) {
                console.error(error);
            }
        });
    }
</script>
