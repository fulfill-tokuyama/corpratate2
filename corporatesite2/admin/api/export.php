<?php
require_once '../../config/database.php';
require_once '../auth.php';

// 認証チェック
requireAuth();

// 検索条件
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // 検索条件の構築
    $where = [];
    $params = [];

    if ($search) {
        $where[] = "(content LIKE :search OR name LIKE :search OR email LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    if ($type) {
        $where[] = "type = :type";
        $params[':type'] = $type;
    }

    if ($status) {
        $where[] = "status = :status";
        $params[':status'] = $status;
    }

    if ($dateFrom) {
        $where[] = "created_at >= :date_from";
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }

    if ($dateTo) {
        $where[] = "created_at <= :date_to";
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // フィードバックの取得
    $sql = "
        SELECT f.*, 
               GROUP_CONCAT(fh.status ORDER BY fh.created_at DESC) as status_history,
               GROUP_CONCAT(fh.notes ORDER BY fh.created_at DESC) as notes_history
        FROM feedback f
        LEFT JOIN feedback_history fh ON f.id = fh.feedback_id
        {$whereClause}
        GROUP BY f.id
        ORDER BY f.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $feedback = $stmt->fetchAll();

    // エクスポート形式の設定
    $type = $_GET['type'] ?? 'csv';
    $filename = 'feedback_' . date('Ymd_His');

    if ($type === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOMの追加（Excelでの文字化け対策）
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // ヘッダーの出力
        fputcsv($output, [
            'ID',
            '日時',
            'タイプ',
            '名前',
            'メール',
            '電話番号',
            '内容',
            'ステータス',
            '対応履歴',
            'メモ履歴'
        ]);
        
        // データの出力
        foreach ($feedback as $row) {
            fputcsv($output, [
                $row['id'],
                $row['created_at'],
                $row['type'],
                $row['name'],
                $row['email'],
                $row['phone'],
                $row['content'],
                $row['status'],
                $row['status_history'],
                $row['notes_history']
            ]);
        }
        
        fclose($output);
    } else if ($type === 'excel') {
        require_once '../../vendor/autoload.php';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // ヘッダーの設定
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', '日時');
        $sheet->setCellValue('C1', 'タイプ');
        $sheet->setCellValue('D1', '名前');
        $sheet->setCellValue('E1', 'メール');
        $sheet->setCellValue('F1', '電話番号');
        $sheet->setCellValue('G1', '内容');
        $sheet->setCellValue('H1', 'ステータス');
        $sheet->setCellValue('I1', '対応履歴');
        $sheet->setCellValue('J1', 'メモ履歴');

        // データの設定
        $row = 2;
        foreach ($feedback as $item) {
            $sheet->setCellValue('A' . $row, $item['id']);
            $sheet->setCellValue('B' . $row, $item['created_at']);
            $sheet->setCellValue('C' . $row, $item['type']);
            $sheet->setCellValue('D' . $row, $item['name']);
            $sheet->setCellValue('E' . $row, $item['email']);
            $sheet->setCellValue('F' . $row, $item['phone']);
            $sheet->setCellValue('G' . $row, $item['content']);
            $sheet->setCellValue('H' . $row, $item['status']);
            $sheet->setCellValue('I' . $row, $item['status_history']);
            $sheet->setCellValue('J' . $row, $item['notes_history']);
            $row++;
        }

        // 列幅の自動調整
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ヘッダーのスタイル設定
        $headerStyle = [
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'CCCCCC',
                ],
            ],
        ];
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

        // 出力
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
    } else {
        throw new Exception('無効なエクスポート形式です。');
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo 'エクスポート中にエラーが発生しました。';
} 