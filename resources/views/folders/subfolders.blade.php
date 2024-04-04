<ul class="subfolders" style="display: none;">
    @foreach ($folder->subfolders as $subfolder)
        <li>
            <a href="#" data-url="{{ route('getFiles', $subfolder) }}"
                onclick="fetchFiles('{{ route('getFiles', $subfolder) }}', 'folder')">
                <i class="fas fa-folder folder-icon"></i>
                <span class="folder-name">{{ $subfolder->name }}</span>
            </a>
        </li>
    @endforeach
</ul>
