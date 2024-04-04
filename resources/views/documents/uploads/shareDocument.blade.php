<!-- Upload Modal -->
<div class="modal fade custom-modal" id="shareDocumentModal" tabindex="-1" role="dialog" aria-labelledby="uploadModalLabel"
    aria-hidden="true">
    <div class="modal-dialog custom-dialog modal-lg" role="document">
        <div class="modal-content custom-content">
            <div class="modal-header custom-header p-1">
                <h5 class="modal-title custom-title" id="uploadModalLabel">Share selected file</h5>
                <button type="button" class="close custom-close" data-bs-toggle="modal"
                    data-bs-target="#shareDocumentModal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3" id="requestDocumentModalBodyId">
                <!-- Upload Form -->
                <form id="shareFolderForm">
                    <input type="hidden" id="sharedId" name="shared_id">
                    <input type="hidden" id="sharedTokenId" name="token">
                    <input type="hidden" id="sharedSlugId" name="slug">
                    <!-- URL Input -->
                    <label for="folderIdInput" class="custom-label col-md-3">URL</label>
                    <div class="form-group col-md-12">
                        <div class="input-group form-group">
                            <input type="url" readonly class="form-control custom-input-lg col-md-9"
                                id="sharedUrlId" name="url">
                            <div class="btn btn-xs" onclick="copyUrl()">Copy</div>
                        </div>
                    </div>
                    <!-- Name Input -->
                    <div class="col-md-12">
                        <label for="selectedDocumentFile" id="selectedDocumentFileText" class="custom-label">Shared
                            Document</label>
                        <div class="form-group">
                            <label id="selectedDocumentFile" class="badge"></label>
                        </div>
                    </div>
                    <!-- Name Input -->
                    <div class="form-group col-md-10">
                        <div class="form-group col-md-8">
                            <label for="folderNameInput" class="custom-label mt-4">Name</label>
                            <input type="text" class="form-control custom-input" id="folderNameInput" name="name">
                        </div>
                        <div class="form-group col-md-4" style="margin-left: 2rem">
                            <label for="folderNameInput" class="custom-label mt-4 ">Valid Until</label>
                            <input type="date" class="form-control custom-input" id="folderNameInput"
                                name="valid_until">
                        </div>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input custom-checkbox" type="checkbox" id="shareVisibilityCheckbox"
                            name="visibility" value="public" checked>
                        <label class="form-check-label custom-check-label" for="shareVisibilityCheckbox">
                            Make private
                        </label>
                    </div>

                </form>
            </div>
            <div class="modal-footer custom-footer p-2">
                <button type="submit" class="btn btn-primary custom-button"
                    onclick="submitShareFormFolder()">Share</button>
                <button type="button" data-bs-toggle="modal" data-bs-target="#shareDocumentModal"
                    class="btn btn-secondary custom-button-close" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
    .badge {
        border-radius: 2rem;
        background-color: purple;
        color: white;
    }
</style>



@once
    <script src="{{ asset('custom-js/share.js') }}"></script>
@endonce
