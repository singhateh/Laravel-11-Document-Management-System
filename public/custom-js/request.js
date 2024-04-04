function requestDocument() {
    var selectedFolder = localStorage.getItem('selectedFolderId');
    document.getElementById('requestFolderNameInput').value = selectedFolder;
    $('.error-message').html('');
}

function submitRequestFormFolder() {
    var form = document.getElementById('requestFolderForm');
    var formData = new FormData(form);
    var $form = $('#requestFolderForm'); // Get the current form


    // Display FormData in console (optional)
    for (var pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }

    formData.append('_token', csrfToken);

    $.ajax({
        url: '/request-document', // Replace with your server endpoint
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            // fetchFiles(response.url);
            $('#requestDocumentModal').modal('hide');
            $('#requestFolderForm')[0].reset();
            fetchFiles(response.url);
        },
        error: function (xhr, status, error) {
            validation(xhr, $form);
        }
    });
}