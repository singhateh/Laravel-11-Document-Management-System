function shareDocument() {
    var selectedFolder = localStorage.getItem('selectedFolderId');
    var selectedDocument = localStorage.getItem('selectedDocumentId');
    var selectedName = localStorage.getItem('selectedShareDocumentFolder');

    var baseUrl = window.location.origin; // Retrieve the base URL
    var shareUrl;
    if (selectedDocument == '' && selectedFolder) {
        // Generate URL for sharing folder with unique token
        var token = generateRandomToken();
        shareUrl = baseUrl + '/folder/share/' + selectedFolder + '/' + token;
        $('#sharedId').val(selectedFolder);
        $('#sharedTokenId').val(token);
        $('#sharedSlugId').val('folder');
        $('#selectedDocumentFileText').html('Shared Folder');
        $('#selectedDocumentFile').html('<i class="fa fa-folder"></i> <strong > ' + selectedName + '</strong>')
    } else if (selectedDocument) {
        // Generate URL for sharing document with unique token
        var token = generateRandomToken();
        shareUrl = baseUrl + '/document/share/' + selectedDocument + '/' + token;
        $('#sharedId').val(selectedDocument);
        $('#sharedTokenId').val(token);
        $('#sharedSlugId').val('document');
        $('#selectedDocumentFileText').html('Shared Document');
        $('#selectedDocumentFile').html('<i class="fa fa-file"></i> <strong"> ' + selectedName + '</strong>')
    } else {
        // Handle case when neither folder nor document is selected
        console.error();
        $('#requestDocumentModalBodyId').html(
            '<strong class="mx-auto text-danger">No folder or document selected for sharing.</strong>')
        return;
    }
    $('#sharedUrlId').val(shareUrl);
}

function submitShareFormFolder() {
    var form = document.getElementById('shareFolderForm');
    var formData = new FormData(form);
    const visibility = $('#shareVisibilityCheckbox').is(':checked') ? 'private' : 'public';

    // Display FormData in console (optional)
    for (var pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }

    formData.append('visibility', visibility);
    formData.append('_token', csrfToken);

    $.ajax({
        url: '/share-document', // Replace with your server endpoint
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            // fetchFiles(response.url);
            $('#shareDocumentModal').modal('hide');
            $('#shareFolderForm')[0].reset();
        },
        error: function (xhr, status, error) {
            console.error('Error uploading files:', error);
        }
    });
}