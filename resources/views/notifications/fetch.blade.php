<?php if($notifications->count() > 0): ?>
    @foreach($notifications as $notification)
    <div class="notification-item" data-id="{{ $notification->id }}">
        <p>{{ $notification->message }}</p>
        <small>{{ $notification->created_at }}</small>
    </div>
    @endforeach
<?php else: ?>
    <p>No notifications found.</p>
<?php endif; ?>