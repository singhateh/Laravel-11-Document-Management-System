<!-- Upload Modal -->
{{-- <div class="modal custom-modal" id="createFolderTagsModal" data-bs-backdrop="static" tabindex="-1" role="dialog"
    aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog custom-dialog modal-lg" role="document">
        <div class="modal-content custom-content">
            <div class="modal-header custom-header p-1">
                <h5 class="modal-title custom-title" id="uploadModalLabel">Create Tags Categories</h5>
                <button type="button" class="close custom-close" onclick="closeFolderTagModal()" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <!-- Upload Form -->
                <form id="FolderTagsForm">
                    <!-- URL Input -->
                    <label for="fileName" class="custom-label">Name</label>
                    <div class="form-group col-md-6">
                        <input type="text" autocomplete="false" placeholder="E.g Status"
                            class="form-control custom-input-lg col-md-6" id="CategoryName" name="category_name">
                    </div>
                    <div class="form-group1 col-md-12">
                        <label for="tags" class="custom-label">Tags</label>
                        <div id="inputContainer">
                            <div class="inputRow input-group">
                                <i class="fa fa-arrows mt-2"></i>
                                <input type="text" name="tags[]" onkeydown="tagsEnter(event)"
                                    class="form-control custom-input">
                                <i class="fa fa-trash-can mt-3 popover" onclick="removeInputRow(this)"></i>
                            </div>
                        </div>
                    </div>
                    <a href="#" class="custom-a" id="addInputBtn" onclick="addInputRow()">Add a line</a>
                </form>
            </div>
            <div class="modal-footer custom-footer p-2">
                <button type="submit" name="type" value="close" class="btn btn-primary custom-button"
                    onclick="saveFolderCategoryTag('close')">Save &
                    Close</button>
                <button type="submit" name="type" value="new" class="btn btn-primary custom-button"
                    onclick="saveFolderCategoryTag('new')">Save &
                    New</button>
                <button type="button" onclick="closeFolderTagModal()" class="btn btn-secondary custom-button-close"
                    data-dismiss="modal">Discard</button>
            </div>
        </div>
    </div>
</div> --}}

<style>
    .custom-a {
        font-size: 14px;
        color: rgb(6, 112, 112);
        margin-top: 5px;
        text-decoration: none;
    }
</style>


<x-modal modalId="createFolderTagsModal" modalTitle="Create Tags Categories" buttonText="Save & Close" backDrop="static"
    modalFade="" onclick="saveFolderCategoryTag('close')" closeModal="closeFolderTagModal"
    onclickOnclose="saveFolderCategoryTag">
    <form id="FolderTagsForm">
        <div class="error-message"></div>
        <!-- URL Input -->
        <label for="fileName" class="custom-label">Name</label>
        <div class="form-group col-md-6">
            <input type="text" autocomplete="false" placeholder="E.g Status"
                class="form-control custom-input-lg col-md-6" id="CategoryName" name="category_name">
        </div>
        <div class="form-group1 col-md-12">
            <label for="tags" class="custom-label">Tags</label>
            <div id="inputContainer">
                <div class="inputRow input-group">
                    <i class="fa fa-arrows mt-2"></i>
                    <input type="text" name="tags[]" onkeydown="tagsEnter(event)" class="form-control custom-input">
                    <i class="fa fa-trash-can mt-3 popover" onclick="removeInputRow(this)"></i>
                </div>
            </div>
        </div>
        <a href="#" class="custom-a" id="addInputBtn" onclick="addInputRow()">Add a line</a>
    </form>
</x-modal>



<script>
    function saveFolderCategoryTag(type) {

        var form = document.getElementById('FolderTagsForm');
        var $form = $('#FolderTagsForm');
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
        formData.append('folder_name', $('#folderNameInput').val());
        formData.append('parent_id', $('#folderParentNameInput').val());

        $.ajax({
            url: @json(route('tags.store')), // Replace with your server endpoint
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                // fetchFiles(response.url);

                if (type === 'close') {
                    closeFolderTagModal();
                    $('#FolderTagsForm')[0].reset();
                } else {
                    $('#FolderTagsForm')[0].reset();
                }
                $('#renderFolderCategoryHtml').html(response.html);

            },
            error: function(xhr, status, error) {
                validation(xhr, $form);
            }
        });
    }


    function FolderTagsModal() {
        $('#createFolderTagsModal').modal('show');
        $('#createFolderModal').modal('hide');
    }

    function closeFolderTagModal() {
        $('#createFolderTagsModal').modal('hide');
        $('#createFolderModal').modal('show');
    }
</script>
