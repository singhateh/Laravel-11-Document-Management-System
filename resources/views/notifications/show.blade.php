@if(isset($notification))
<div class="notification-detail">
    <h2>Notification Details</h2>
    <div class="notification-message">
        <p>{{ $notification->message }}</p>
    </div>
    <div class="notification-meta">
        <small>Created: {{ $notification->created_at }}</small>
        <br>
        <small>Status: {{ $notification->status }}</small>
    </div>
</div>
@else
    <p>Notification not found.</p>
@endif