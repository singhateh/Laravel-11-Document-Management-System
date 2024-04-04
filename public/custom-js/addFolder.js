function saveFolderForm(route) {

    var form = document.getElementById('FolderCreateForm');
    var $form = $('#FolderCreateForm');
    var formData = new FormData();

    // Iterate through form elements
    for (var pair of new FormData(form)) {
        // Check if the input value is not empty
        if (pair[1].trim() !== '') {
            // Add the non-empty input to formData
            formData.append(pair[0], pair[1]);
        }
    }

    formData.append('_token', csrfToken);

    $.ajax({
        url: route, // Replace with your server endpoint
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            closeFolderModal();
            $('#renderFolderTableHtml').html(response.html);
        },
        error: function (xhr, status, error) {
            validation(xhr, $form);
        }
    });
}


function createFolderModal() {
    $('#createFolderModal').modal('show');
}

function closeFolderModal() {
    var $modal = $('#createFolderModal');

    // Check if the modal exists
    if ($modal.length) {
        $modal.modal('hide');

        // Reset form if it exists
        var $form = $('#FolderCreateForm');
        if ($form.length) {
            $form[0].reset();
        }

        // Clear HTML content if it exists
        var $categoryHtml = $modal.find('#renderFolderCategoryHtml');
        if ($categoryHtml.length) {
            $categoryHtml.html('');
        }

        // Clear error messages if they exist
        var $errorMessage = $modal.find('.error-message');
        if ($errorMessage.length) {
            $errorMessage.html('');
        }

        // Remove invalid feedback elements and classes if they exist
        $modal.find('.invalid-feedback').remove();
        $modal.find('.is-invalid').removeClass('is-invalid');
    }
}
