$target = 'C:\\Users\\admin\\Documents\\GitHub\\School-Management-Laravel\\app\\Http\\Controllers\\Api'

$content = @'
<?php

namespace App\Http\Controllers\Api;
'@

[IO.File]::WriteAllText((Join-Path $target 'test_student.php'), $content)
Write-Host 'Written test'