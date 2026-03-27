<?php if(isset($data) && $data->count() > 0): ?>
    @foreach($data as $notification)
    <div class="notification-item" data-id="{{ $notification->id }}">
        <p>{{ $notification->message }}</p>
        <small>{{ $notification->created_at }}</small>
    </div>
    @endforeach
<?php else: ?>
    <p>No notifications found.</p>
<?php endif; ?>