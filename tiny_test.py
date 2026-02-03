content = b"""<?php
namespace App\Http\Controllers\Api;
class T { public function x($r) { $v = 1; } }
"""
print("len:", len(content), "dollars:", content.count(b"$"))
