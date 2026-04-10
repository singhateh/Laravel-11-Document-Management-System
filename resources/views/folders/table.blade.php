<ul class="folder-list">
    @forelse ($folders as $folder)
        @include('folders.parentfolder', ['folder' => $folder])
    @empty
        <li class="folder-item text-muted">No workspaces found.</li>
    @endforelse
</ul>
