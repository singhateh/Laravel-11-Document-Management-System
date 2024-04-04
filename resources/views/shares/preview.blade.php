<!-- Preview Modal -->
<div class="modal fade" id="documentPreviewModal" tabindex="-1" data-backdrop="static" role="dialog"
    aria-labelledby="documentPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="documentPreviewModalLabel">Document Preview</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <iframe id="previewIframe" frameborder="0" style="display: none"></iframe>
                <video id="previewVideo" frameborder="0" style="display: none"></video>
                <img id="previewImage" frameborder="0" style="display: none">
            </div>
        </div>
    </div>
</div>

<style>
    /* Custom styles for the modal */
    .modal-content {
        border-radius: 0;
    }

    .modal-header {
        border-bottom: none;
    }

    .modal-body {
        padding: 0;
    }

    #previewIframe {
        width: 100%;
        height: calc(100vh - 120px);
        /* Adjust height as needed, leaving space for modal header and footer */
        border: none;
        object-fit: scale-down !important;
    }

    #previewVideo {
        width: 100%;
        height: calc(100vh - 120px);
        /* Adjust height as needed, leaving space for modal header and footer */
        border: none;
        object-fit: scale-down !important;
    }

    #previewImage {
        width: 100%;
        height: calc(100vh - 120px);
        /* Adjust height as needed, leaving space for modal header and footer */
        border: none;
        object-fit: scale-down !important;
    }
</style>
