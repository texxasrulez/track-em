<?php
namespace TrackEm\Controllers;

use TrackEm\Models\VisitorRepo;

class VisitorsController {
    /** @var \PDO */
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    private function repo() {
        return new VisitorRepo($this->pdo);
    }

    // Route: ?p=api.visitors.log
    public function api_visitors_log() {
        header('Content-Type: application/json; charset=utf-8');
        $ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] :
              (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $path = isset($_POST['path']) ? $_POST['path'] : (isset($_GET['path']) ? $_GET['path'] : (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''));
        $ref = isset($_POST['ref']) ? $_POST['ref'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');

        // Optional geo passthrough
        $geo = array();
        foreach (array('lat','lon','city','country') as $k){
            if (isset($_POST[$k])) $geo[$k] = $_POST[$k];
            elseif (isset($_GET[$k])) $geo[$k] = $_GET[$k];
        }

        try{
            $id = $this->repo()->log($ip, $ua, $path, $ref, $geo);
            echo json_encode(array('ok'=>true, 'id'=>$id), JSON_UNESCAPED_SLASHES);
        }catch(\Exception $e){
            http_response_code(500);
            echo json_encode(array('ok'=>false, 'error'=>$e->getMessage()), JSON_UNESCAPED_SLASHES);
        }
        exit;
    }

    // Route: ?p=api.visitors
    public function api_visitors() {
        header('Content-Type: application/json; charset=utf-8');
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
        $search = isset($_GET['q']) ? (string)$_GET['q'] : "";
        try{
            $rows = $this->repo()->aggregateByIp($limit, $search);
            echo json_encode(array('items'=>$rows), JSON_UNESCAPED_SLASHES);
        }catch(\Exception $e){
            http_response_code(500);
            echo json_encode(array('ok'=>false, 'error'=>$e->getMessage()), JSON_UNESCAPED_SLASHES);
        }
        exit;
    }

    // Route: ?p=api.visitors.ip&ip=1.2.3.4
    public function api_visitors_ip() {
        header('Content-Type: application/json; charset=utf-8');
        $ip = isset($_GET['ip']) ? $_GET['ip'] : '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
        if ($ip === '') { http_response_code(400); echo json_encode(array('ok'=>false,'error'=>'missing ip')); exit; }
        try{
            $rows = $this->repo()->listByIp($ip, $limit);
            echo json_encode(array('items'=>$rows), JSON_UNESCAPED_SLASHES);
        }catch(\Exception $e){
            http_response_code(500);
            echo json_encode(array('ok'=>false, 'error'=>$e->getMessage()), JSON_UNESCAPED_SLASHES);
        }
        exit;
    }

    // Route: ?p=admin.visitors
    public function admin_visitors() {
        // Expect that the app loads a layout that echoes this view.
        include __DIR__ . '/../views/admin/visitors.php';
        exit;
    }
}
