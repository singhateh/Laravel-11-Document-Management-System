@if (!Route::is('home'))
    <nav class="navbar navbar-serach navbar-expand-lg navbar-light bg-light mb-3">
        <div class="container">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse d-flex justify-content-between" id="navbarNav">
                @if (Route::is('documents.index'))
                    <ul class="navbar-nav mr-auto1 gap-2">
                        <li class="nav-item active">
                            <a class="btn btn-info" href="#" onclick="openModal('uploadFolderModal')">
                                <i class="fas fa-upload"></i> Upload Folder <span class="sr-only">(current)</span>
                            </a>
                        </li>
                        <li class="nav-item active">
                            <a class="btn btn-info" href="#" onclick="uploadFiles()">
                                <i class="fas fa-upload"></i> Upload <span class="sr-only">(current)</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-info" href="#" onclick="openModal('uploadModal')">
                                <i class="fas fa-link"></i> Add Url
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-info" href="#" onclick="requestDocument()" data-bs-toggle="modal"
                                data-bs-target="#requestDocumentModal">
                                <i class="fas fa-file-alt"></i> Request Document
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-info" href="#" data-bs-toggle="modal" onclick="shareDocument()"
                                data-bs-target="#shareDocumentModal">
                                <i class="fas fa-share-alt"></i> Share
                            </a>
                        </li>
                    </ul>
                @else
                    @if (!Route::is('getSharedDocuments'))
                        <ul class="navbar-nav mr-auto1 gap-2">
                            <li class="nav-item active">
                                <a class="btn btn-info" href="#" onclick="openModal('createFolderModal')">
                                    <i class="fas fa-plus"></i> Create <span class="sr-only">(current)</span>
                                </a>
                            </li>
                            <li class="nav-item active">
                                <a class="btn btn-info" href="#" onclick="downloadFolder()">
                                    <i class="fa fa-cloud-download"></i> Download <span class="sr-only">(current)</span>
                                </a>
                            </li>
                            <li class="nav-item active">
                                <a class="btn btn-info" id="delete-button" href="#"
                                    onclick="deleteSelectedRecord()">
                                    <i class="fas fa-trash-can"></i> Delete Selected <span
                                        class="sr-only">(current)</span>
                                </a>
                            </li>
                        </ul>
                    @elseif(isset($shareDocument) && $shareDocument->slug === 'folder')
                        <ul class="navbar-nav mr-auto1 gap-2">
                            <li class="nav-item active">
                                <a class="btn btn-info" href="#" onclick="downloadFolder()">
                                    <i class="fa fa-cloud-download"></i> Download <span class="sr-only">(current)</span>
                                </a>
                            </li>
                        </ul>
                    @endif

                @endif
            </div>
        </div>
    </nav>
@endif

<style>
    .content .navbar-serach {
        margin-top: 1.7rem;
    }

    .search-container {
        display: flex;
        align-items: center;
    }

    .dropdown {
        margin-right: 10px;
    }

    .form-control {
        flex: 1;
    }

    .search-container {
        display: flex;
        align-items: center;
    }

    .dropdown-section {
        flex-grow: 1;
    }

    .separator {
        border-left: 1px solid #ccc;
        height: 30px;
        /* Adjust height as needed */
        margin: 0 10px;
        /* Adjust spacing between sections */
    }

    .section-title {
        font-weight: bold;
    }

    .dropdown-menu {
        display: none;
        position: absolute;
        background-color: #fff;
        min-width: 160px;
        z-index: 1;
    }

    .dropdown-section:hover .dropdown-menu {
        display: block;
    }
</style>
