<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../admin/config.php';

class DeleteImageTest extends TestCase {
    private $projectRoot;

    protected function setUp(): void {
        $this->projectRoot = dirname(__DIR__);
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        // ensure admin session markers
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = 'test';
        $_SESSION['admin_pw_version'] = get_admin_password_version();
    }

    public function testDeleteImageMovesToTrashAndRestores() {
        // find a sample image
        $sample = null;
        $dirs = [$this->projectRoot . '/uploads/images/gallery', $this->projectRoot . '/uploads/images/hero', $this->projectRoot . '/uploads/images/logo'];
        foreach ($dirs as $d) {
            if (!is_dir($d)) continue;
            $it = new DirectoryIterator($d);
            foreach ($it as $f) { if ($f->isFile()) { $sample = $f->getPathname(); break 2; } }
        }
        $this->assertNotNull($sample, 'No sample image available for delete test');

        $uniq = time() . '-' . bin2hex(random_bytes(4));
        $ext = pathinfo($sample, PATHINFO_EXTENSION);
        $tmpRel = 'tmp-delete-' . $uniq . '.' . $ext;
        $tmpPath = $this->projectRoot . '/uploads/images/' . $tmpRel;
        $this->assertTrue(copy($sample, $tmpPath));

        // simulate POST to delete-image.php
        $_POST = ['filename' => $tmpRel, 'csrf_token' => generate_csrf_token()];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        ob_start();
        include $this->projectRoot . '/admin/delete-image.php';
        $out = ob_get_clean();
        $resp = json_decode($out, true);
        $this->assertIsArray($resp);
        $this->assertTrue((bool)$resp['success']);

        // find the trashed file
        $trash = $this->projectRoot . '/uploads/trash/';
        $found = null;
        if (is_dir($trash)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($trash, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (!$f->isFile()) continue;
                if (strpos($f->getFilename(), $uniq) !== false) { $found = $f->getPathname(); break; }
            }
        }
        $this->assertNotNull($found, 'Trashed file not found');

        // restore
        $this->assertTrue(rename($found, $tmpPath) || (copy($found, $tmpPath) && unlink($found)));
        // cleanup
        if (file_exists($found . '.json')) @unlink($found . '.json');
        @unlink($tmpPath);
    }
}
