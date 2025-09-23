<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>File Upload Debugger</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f4f4f9; color: #333; }
        h2, h3, h4 { color: #444; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 800px; margin: 20px auto; }
        form { margin-bottom: 20px; }
        input[type='file'] { border: 1px solid #ccc; padding: 10px; border-radius: 4px; }
        input[type='submit'] { background-color: #51CACF; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        input[type='submit']:hover { background-color: #45a0a5; }
        .result { border: 1px solid #e0e0e0; padding: 20px; margin-top: 20px; background-color: #fdfdfd; border-radius: 6px; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        code { background-color: #e9ecef; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
        pre { background-color: #333; color: #f1f1f1; padding: 15px; border-radius: 4px; white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>

    <div class="container">
        <h2>File Upload Debugger</h2>
        <p>This tool helps diagnose file upload issues by checking permissions and showing detailed information.</p>
        
        <form action="debug_upload.php" method="post" enctype="multipart/form-data">
            <p><strong>Step 1:</strong> Select a small file (e.g., a .txt or .jpg) to upload.</p>
            <input type="file" name="debug_file" id="debug_file" required>
            <br><br>
            <input type="submit" value="Test Upload" name="submit">
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            echo '<div class="result">';
            echo '<h3>Debug Results</h3>';

            // 1. Check who the script is running as
            $user = 'unknown';
            if (function_exists('shell_exec')) {
                $user = shell_exec('whoami');
            }
            echo "<p><strong>PHP script running as user:</strong> <code>" . htmlspecialchars(trim($user)) . "</code></p>";

            // 2. Check permissions on the uploads directory
            $upload_dir = 'uploads/';
            $absolute_upload_dir = realpath($upload_dir);
            echo "<p><strong>Checking directory:</strong> <code>" . $absolute_upload_dir . "</code></p>";
            
            if ($absolute_upload_dir && is_dir($absolute_upload_dir)) {
                if (is_writable($absolute_upload_dir)) {
                    echo "<p><strong>Directory permissions:</strong> <span class='success'>OK - Directory is writable.</span></p>";
                } else {
                    echo "<p><strong>Directory permissions:</strong> <span class='error'>ERROR - Directory is NOT writable.</span></p>";
                    echo "<p>The user `" . htmlspecialchars(trim($user)) . "` needs write permissions. Please run this command in your terminal:</p>";
                    echo "<code>sudo chown -R " . htmlspecialchars(trim($user)) . ":" . htmlspecialchars(trim($user)) . " " . $absolute_upload_dir . "</code>";
                }
            } else {
                 echo "<p><strong>Directory check:</strong> <span class='error'>ERROR - The directory `{$upload_dir}` does not seem to exist or is not accessible.</span></p>";
            }

            // 3. Check if file was uploaded
            if (isset($_FILES['debug_file']) && $_FILES['debug_file']['error'] === UPLOAD_ERR_OK) {
                echo '<h4>PHP $_FILES Array Content:</h4>';
                echo '<pre>';
                print_r($_FILES['debug_file']);
                echo '</pre>';

                $original_name = $_FILES['debug_file']['name'];
                $tmp_name = $_FILES['debug_file']['tmp_name'];
                $destination = $upload_dir . basename($original_name);

                echo "<p><strong>Attempting to move file from:</strong> <code>" . htmlspecialchars($tmp_name) . "</code></p>";
                echo "<p><strong>...to destination:</strong> <code>" . htmlspecialchars($destination) . "</code></p>";

                // 4. Attempt to move the file
                if (move_uploaded_file($tmp_name, $destination)) {
                    echo "<h4>Result: <span class='success'>SUCCESS! File uploaded.</span></h4>";
                    echo "<p>You should now see the file `" . htmlspecialchars($original_name) . "` inside the `" . $absolute_upload_dir . "` directory.</p>";
                } else {
                    echo "<h4>Result: <span class='error'>FAILURE: move_uploaded_file() failed.</span></h4>";
                    $error = error_get_last();
                    if ($error) {
                        echo "<p><strong>Last PHP Error:</strong> " . htmlspecialchars($error['message']) . "</p>";
                    }
                }
            } else {
                echo '<h4>File Upload Status:</h4>';
                echo "<p class='error'>No file was uploaded or an error occurred during upload.</p>";
                if (isset($_FILES['debug_file']['error'])) {
                    $upload_errors = [
                        UPLOAD_ERR_INI_SIZE   => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
                        UPLOAD_ERR_FORM_SIZE  => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.",
                        UPLOAD_ERR_PARTIAL    => "The uploaded file was only partially uploaded.",
                        UPLOAD_ERR_NO_FILE    => "No file was uploaded.",
                        UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
                        UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
                        UPLOAD_ERR_EXTENSION  => "A PHP extension stopped the file upload.",
                    ];
                    $error_code = $_FILES['debug_file']['error'];
                    echo "<p><strong>Error Code:</strong> " . $error_code . " - " . ($upload_errors[$error_code] ?? 'Unknown error') . "</p>";
                }
                 echo '<h4>PHP $_FILES Array Content:</h4>';
                echo '<pre>';
                print_r($_FILES);
                echo '</pre>';
            }

            echo '</div>';
        }
        ?>

    </div>
</body>
</html>
