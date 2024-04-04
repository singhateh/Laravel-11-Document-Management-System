@php
    use App\Helpers\FolderHelper;
@endphp

<li class="folder-item" data-folder-id="{{ $folder->id }}">
    <a href="#" data-url="{{ route('getFiles', $folder) }}"
        onclick="fetchFiles('{{ route('getFiles', $folder) }}', 'folder')">
        <span class="folder-content">
            <i class="fas fa-folder folder-icon"></i>
            <span class="folder-name">{{ $folder->name }}</span>
        </span>
    </a>

    @if ($folder->subfolders->isNotEmpty())
        <button class="toggle-subfolders-btn" onclick="toggleSubfolders(this)">
            <i class="fas fa-chevron-down"></i>
        </button>
        <ul class="subfolders" style="display: none;">
            {!! FolderHelper::generateSidebarMenu($folder->id, $depth + 1) !!}
        </ul>
    @endif
</li>
