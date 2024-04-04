<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New File Request</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            text-align: center;
        }

        p {
            color: #666;
            margin-bottom: 20px;
        }

        ul {
            list-style: none;
            padding: 0;
        }

        li {
            margin-bottom: 10px;
        }

        strong {
            font-weight: bold;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>New File Request</h1>
        <p>Hello,</p>
        <p>You have received a new file request:</p>
        <ul>
            <li><strong>Name:</strong> {{ $fileRequest->name }}</li>
            <li><strong>Requested To:</strong> {{ $fileRequest->request_to }}</li>
            <!-- Add more details as needed -->
        </ul>
        <p>Thank you.</p>
        <div class="footer">
            This email was sent automatically. Please do not reply.
        </div>
    </div>
</body>

</html>
