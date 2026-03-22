<?php   
header('Content-Type: application/json');
if (isset($_POST['expr'])) {
    $expr = $_POST['expr'];
    $cleanExpr = preg_replace('/[^0-9\+\-\*\/\.\^sqrt]/', '', $expr);

    $fixedExpr = preg_replace('/sqrt([0-9\.]+)/', 'sqrt($1)', $cleanExpr);
    $finalExpr = str_replace('^', '**', $fixedExpr);

    try{
        $result = eval("return $finalExpr;");

        if ($result === false && $finalExpr != '0'){
            throw new Exception("Ошибка вычисления");
        }

        echo json_encode([
            'status' => 'success',
            'result' => $result,
            'history' => $expr . '=' . $result
        ]);
    }
    catch (Throwable $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Ошибка в выражении'
        ]);
    }
}