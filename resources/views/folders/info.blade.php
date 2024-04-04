    @if (!is_null($folderInfo))
        <div class="folder-info">
            <img style="display: none" src="{{ asset('img/folder.png') }}" alt="" id="getFolderIcon">
            <h2 class="folder-info-name" title="{{ $folderInfo['folder_name'] }}"><i class="fa fa-folder"></i>
                {{ Str::limit($folderInfo['folder_name'], 10) }}</h2>
            <ul class="folder-details">
                <li>
                    <i class="fas fa-file"></i> <!-- Icon for Total Documents -->
                    <span class="detail-label">Total Documents:</span>
                    <span class="detail-value">{{ $folderInfo['num_documents']['total'] }}</span>
                </li>
                <li>
                    <i class="fas fa-globe"></i> <!-- Icon for Public Documents -->
                    <span class="detail-label">Public Documents:</span>
                    <span class="detail-value">{{ $folderInfo['num_documents']['public'] }}</span>
                </li>
                <li>
                    <i class="fas fa-lock"></i> <!-- Icon for Private Documents -->
                    <span class="detail-label">Private Documents:</span>
                    <span class="detail-value">{{ $folderInfo['num_documents']['private'] }}</span>
                </li>
                <li>
                    <i class="fas fa-folder"></i> <!-- Icon for Total Size -->
                    <span class="detail-label">Total Size:</span>
                    <span class="detail-value">{{ $folderInfo['total_size'] }}</span>
                </li>
                <li>
                    <i class="fas fa-calendar-plus"></i> <!-- Icon for Created At -->
                    <span class="detail-label">Created At:</span>
                    <span class="detail-value">{{ $folderInfo['created_at'] }}</span>
                </li>
                <li>
                    <i class="fas fa-calendar-check"></i> <!-- Icon for Updated At -->
                    <span class="detail-label">Updated At:</span>
                    <span class="detail-value">{{ $folderInfo['updated_at'] }}</span>
                </li>
            </ul>
        </div>
    @endif


    <style>
        .folder-info {
            border-radius: 5px;
            margin-bottom: 20px;
            color: rgba(245, 245, 245, 0.757);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .folder-info-name {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .folder-details {
            list-style-type: none;
            padding: 0;
            margin: 0;
            font-size: 12px;
        }

        .folder-details li {
            margin-bottom: 3px;
        }

        .detail-label {
            font-weight: 500;
        }

        .detail-value {
            margin-left: 10px;
        }

        .document-properties .card>.card-header-info img {
            display: block;
            margin: 0 auto;
            width: auto;
            height: 100px;
            object-fit: fill;
            filter: invert(100%);
        }
    </style>
