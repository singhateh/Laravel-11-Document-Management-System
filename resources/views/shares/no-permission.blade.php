<style>
    .custom-container {
        margin-top: 120px;
        width: 50%;
        margin: auto;
    }

    .custom-permission {
        border: 1px solid #ddd;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        padding: 20px;
        display: block;
    }

    .custom-permission .card-title {
        color: #333;
        font-size: 1.25rem;
        margin-bottom: 15px;
    }

    .custom-permission label {
        color: #666;
        margin-bottom: 5px;
        font-weight: bold;
    }

    .custom-permission input[type="email"],
    .custom-permission textarea {
        border: 1px solid #ccc;
        padding: 8px;
        width: 100%;
        border-radius: 5px;
        margin-bottom: 10px;
    }

    .custom-permission .form-text {
        font-size: 0.875rem;
        color: #6c757d;
    }

    .custom-permission .btn {
        background-color: #007bff;
        border-color: #007bff;
        color: #fff;
        border-radius: 5px;
        padding: 8px 20px;
        font-size: 1rem;
        cursor: pointer;
    }

    .custom-permission .btn:hover {
        background-color: #0056b3;
        border-color: #0056b3;
    }
</style>
<br><br>
<div class="custom-container">
    <div class="custom-permission">
        <h5 class="card-title">You need permission</h5>
        <div class="form-group1">
            <label for="inputEmail">Your Email</label><br>
            <input type="email" class="form-control" id="inputEmail" aria-describedby="emailHelp"
                placeholder="Enter email">
            <small id="emailHelp" class="form-text text-muted">We'll never share your email with
                anyone else.</small>
        </div>
        <div class="form-group1"><br>
            <label for="inputMessage">Message</label>
            <textarea class="form-control" id="inputMessage" rows="3" placeholder="Enter your message"></textarea>
        </div>
        <button type="submit" class="btn">Request Access</button>
    </div>
</div>
