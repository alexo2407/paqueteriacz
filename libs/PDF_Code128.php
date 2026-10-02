<?php
/**
 * PDF_Code128 - Clase de extensión para FPDF con soporte nativo de Código de Barras Code 128
 *
 * Permite dibujar códigos de barras Code 128 (A, B y C) vectoriales de alta precisión
 * directamente sobre el documento PDF sin requerir librerías gráficas externas.
 */

if (!class_exists('FPDF')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class PDF_Code128 extends FPDF
{
    protected $T128;
    protected $ABCset = "";
    protected $Aset = "";
    protected $Bset = "";
    protected $Cset = "";
    protected $SetFrom;
    protected $SetTo;
    protected $JStart = ["A" => 103, "B" => 104, "C" => 105];
    protected $JSwap = ["A" => 101, "B" => 100, "C" => 99];

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);

        $this->T128[] = [2, 1, 2, 2, 2, 2]; // 0
        $this->T128[] = [2, 2, 2, 1, 2, 2]; // 1
        $this->T128[] = [2, 2, 2, 2, 2, 1]; // 2
        $this->T128[] = [1, 2, 1, 2, 2, 3]; // 3
        $this->T128[] = [1, 2, 1, 3, 2, 2]; // 4
        $this->T128[] = [1, 3, 1, 2, 2, 2]; // 5
        $this->T128[] = [1, 2, 2, 2, 1, 3]; // 6
        $this->T128[] = [1, 2, 2, 3, 1, 2]; // 7
        $this->T128[] = [1, 3, 2, 2, 1, 2]; // 8
        $this->T128[] = [2, 2, 1, 2, 1, 3]; // 9
        $this->T128[] = [2, 2, 1, 3, 1, 2]; // 10
        $this->T128[] = [2, 3, 1, 2, 1, 2]; // 11
        $this->T128[] = [1, 1, 2, 2, 3, 2]; // 12
        $this->T128[] = [1, 2, 2, 1, 3, 2]; // 13
        $this->T128[] = [1, 2, 2, 2, 3, 1]; // 14
        $this->T128[] = [1, 1, 3, 2, 2, 2]; // 15
        $this->T128[] = [1, 2, 3, 1, 2, 2]; // 16
        $this->T128[] = [1, 2, 3, 2, 2, 1]; // 17
        $this->T128[] = [2, 2, 3, 2, 1, 1]; // 18
        $this->T128[] = [2, 2, 1, 1, 3, 2]; // 19
        $this->T128[] = [2, 2, 1, 2, 3, 1]; // 20
        $this->T128[] = [2, 1, 3, 2, 1, 2]; // 21
        $this->T128[] = [2, 2, 3, 1, 1, 2]; // 22
        $this->T128[] = [3, 1, 2, 1, 3, 1]; // 23
        $this->T128[] = [3, 1, 1, 2, 2, 2]; // 24
        $this->T128[] = [3, 2, 1, 1, 2, 2]; // 25
        $this->T128[] = [3, 2, 1, 2, 2, 1]; // 26
        $this->T128[] = [3, 1, 2, 2, 1, 2]; // 27
        $this->T128[] = [3, 2, 2, 1, 1, 2]; // 28
        $this->T128[] = [3, 2, 2, 2, 1, 1]; // 29
        $this->T128[] = [2, 1, 2, 1, 2, 3]; // 30
        $this->T128[] = [2, 1, 2, 3, 2, 1]; // 31
        $this->T128[] = [2, 3, 2, 1, 2, 1]; // 32
        $this->T128[] = [1, 1, 1, 3, 2, 3]; // 33
        $this->T128[] = [1, 3, 1, 1, 2, 3]; // 34
        $this->T128[] = [1, 3, 1, 3, 2, 1]; // 35
        $this->T128[] = [1, 1, 2, 3, 1, 3]; // 36
        $this->T128[] = [1, 3, 2, 1, 1, 3]; // 37
        $this->T128[] = [1, 3, 2, 3, 1, 1]; // 38
        $this->T128[] = [2, 1, 1, 3, 1, 3]; // 39
        $this->T128[] = [2, 3, 1, 1, 1, 3]; // 40
        $this->T128[] = [2, 3, 1, 3, 1, 1]; // 41
        $this->T128[] = [1, 1, 2, 1, 3, 3]; // 42
        $this->T128[] = [1, 1, 2, 3, 3, 1]; // 43
        $this->T128[] = [1, 3, 2, 1, 3, 1]; // 44
        $this->T128[] = [1, 1, 3, 1, 2, 3]; // 45
        $this->T128[] = [1, 1, 3, 3, 2, 1]; // 46
        $this->T128[] = [1, 3, 3, 1, 2, 1]; // 47
        $this->T128[] = [3, 1, 3, 1, 2, 1]; // 48
        $this->T128[] = [2, 1, 1, 3, 3, 1]; // 49
        $this->T128[] = [2, 3, 1, 1, 3, 1]; // 50
        $this->T128[] = [2, 1, 3, 1, 1, 3]; // 51
        $this->T128[] = [2, 1, 3, 3, 1, 1]; // 52
        $this->T128[] = [2, 1, 3, 1, 3, 1]; // 53
        $this->T128[] = [3, 1, 1, 1, 2, 3]; // 54
        $this->T128[] = [3, 1, 1, 3, 2, 1]; // 55
        $this->T128[] = [3, 3, 1, 1, 2, 1]; // 56
        $this->T128[] = [3, 1, 2, 1, 1, 3]; // 57
        $this->T128[] = [3, 1, 2, 3, 1, 1]; // 58
        $this->T128[] = [3, 3, 2, 1, 1, 1]; // 59
        $this->T128[] = [3, 1, 4, 1, 1, 1]; // 60
        $this->T128[] = [2, 2, 1, 4, 1, 1]; // 61
        $this->T128[] = [4, 3, 1, 1, 1, 1]; // 62
        $this->T128[] = [1, 1, 1, 2, 2, 4]; // 63
        $this->T128[] = [1, 1, 1, 4, 2, 2]; // 64
        $this->T128[] = [1, 2, 1, 1, 2, 4]; // 65
        $this->T128[] = [1, 2, 1, 4, 2, 1]; // 66
        $this->T128[] = [1, 4, 1, 1, 2, 2]; // 67
        $this->T128[] = [1, 4, 1, 2, 2, 1]; // 68
        $this->T128[] = [1, 1, 2, 2, 1, 4]; // 69
        $this->T128[] = [1, 1, 2, 4, 1, 2]; // 70
        $this->T128[] = [1, 2, 2, 1, 1, 4]; // 71
        $this->T128[] = [1, 2, 2, 4, 1, 1]; // 72
        $this->T128[] = [1, 4, 2, 1, 1, 2]; // 73
        $this->T128[] = [1, 4, 2, 2, 1, 1]; // 74
        $this->T128[] = [2, 4, 1, 2, 1, 1]; // 75
        $this->T128[] = [2, 2, 1, 1, 1, 4]; // 76
        $this->T128[] = [4, 1, 3, 1, 1, 1]; // 77
        $this->T128[] = [2, 4, 1, 1, 1, 2]; // 78
        $this->T128[] = [1, 3, 4, 1, 1, 1]; // 79
        $this->T128[] = [1, 1, 1, 2, 4, 2]; // 80
        $this->T128[] = [1, 2, 1, 1, 4, 2]; // 81
        $this->T128[] = [1, 2, 1, 2, 4, 1]; // 82
        $this->T128[] = [1, 1, 4, 2, 1, 2]; // 83
        $this->T128[] = [1, 2, 4, 1, 1, 2]; // 84
        $this->T128[] = [1, 2, 4, 2, 1, 1]; // 85
        $this->T128[] = [4, 1, 1, 2, 1, 2]; // 86
        $this->T128[] = [4, 2, 1, 1, 1, 2]; // 87
        $this->T128[] = [4, 2, 1, 2, 1, 1]; // 88
        $this->T128[] = [2, 1, 2, 1, 4, 1]; // 89
        $this->T128[] = [2, 1, 4, 1, 2, 1]; // 90
        $this->T128[] = [4, 1, 2, 1, 2, 1]; // 91
        $this->T128[] = [1, 1, 1, 1, 4, 3]; // 92
        $this->T128[] = [1, 1, 1, 3, 4, 1]; // 93
        $this->T128[] = [1, 3, 1, 1, 4, 1]; // 94
        $this->T128[] = [1, 1, 4, 1, 1, 3]; // 95
        $this->T128[] = [1, 1, 4, 3, 1, 1]; // 96
        $this->T128[] = [4, 1, 1, 1, 1, 3]; // 97
        $this->T128[] = [4, 1, 1, 3, 1, 1]; // 98
        $this->T128[] = [1, 1, 3, 1, 4, 1]; // 99
        $this->T128[] = [1, 1, 4, 1, 3, 1]; // 100
        $this->T128[] = [3, 1, 1, 1, 4, 1]; // 101
        $this->T128[] = [4, 1, 1, 1, 3, 1]; // 102
        $this->T128[] = [2, 1, 1, 4, 1, 2]; // 103
        $this->T128[] = [2, 1, 1, 2, 1, 4]; // 104
        $this->T128[] = [2, 1, 1, 2, 3, 2]; // 105
        $this->T128[] = [2, 3, 3, 1, 1, 1]; // 106
        $this->T128[] = [2, 1];             // 107 : Stop

        for ($i = 32; $i <= 95; $i++) {
            $this->ABCset .= chr($i);
        }
        $this->Aset = $this->ABCset;
        $this->Bset = $this->ABCset;
        for ($i = 0; $i <= 31; $i++) {
            $this->ABCset .= chr($i);
            $this->Aset   .= chr($i);
        }
        for ($i = 96; $i <= 127; $i++) {
            $this->ABCset .= chr($i);
            $this->Bset   .= chr($i);
        }
        for ($i = 200; $i <= 210; $i++) {
            $this->ABCset .= chr($i);
            $this->Aset   .= chr($i);
            $this->Bset   .= chr($i);
        }
        $this->Cset = "0123456789" . chr(206);

        $this->SetFrom = ["A" => "", "B" => ""];
        $this->SetTo   = ["A" => "", "B" => ""];

        for ($i = 0; $i < 96; $i++) {
            $this->SetFrom["A"] .= chr($i);
            $this->SetFrom["B"] .= chr($i + 32);
            $this->SetTo["A"]   .= chr(($i < 32) ? $i + 64 : $i - 32);
            $this->SetTo["B"]   .= chr($i);
        }
        for ($i = 96; $i < 107; $i++) {
            $this->SetFrom["A"] .= chr($i + 104);
            $this->SetFrom["B"] .= chr($i + 104);
            $this->SetTo["A"]   .= chr($i);
            $this->SetTo["B"]   .= chr($i);
        }
    }

    /**
     * Dibuja un código de barras Code 128
     *
     * @param float $x Coordenada X
     * @param float $y Coordenada Y
     * @param string $code Texto a codificar
     * @param float $w Ancho total deseado del código
     * @param float $h Alto del código
     */
    public function Code128($x, $y, $code, $w, $h)
    {
        $Aguid = "";
        $Bguid = "";
        $Cguid = "";
        $len = strlen($code);

        for ($i = 0; $i < $len; $i++) {
            $char = $code[$i];
            $needle = (string)$char;
            $Aguid .= (strpos($this->Aset, $needle) === false) ? "N" : "O";
            $Bguid .= (strpos($this->Bset, $needle) === false) ? "N" : "O";
            $Cguid .= (strpos($this->Cset, $needle) === false) ? "N" : "O";
        }

        $SminiC = "OOOO";
        $IminiC = 4;
        $crypt = "";

        while ($code != "") {
            $i = strpos($Cguid, $SminiC);
            if ($i !== false) {
                $Aguid = substr($Aguid, 0, $i);
                $Bguid = substr($Bguid, 0, $i);
            }
            $madeA = strpos($Aguid, "N");
            if ($madeA === false) {
                $madeA = strlen($Aguid);
            }
            $madeB = strpos($Bguid, "N");
            if ($madeB === false) {
                $madeB = strlen($Bguid);
            }
            $made = max($madeA, $madeB);
            if ($made > 0) {
                $set = ($madeA >= $madeB) ? "A" : "B";
                $crypt .= chr(($crypt != "") ? $this->JSwap[$set] : $this->JStart[$set]);
                $crypt .= strtr(substr($code, 0, $made), $this->SetFrom[$set], $this->SetTo[$set]);
                $code  = substr($code, $made);
                $Aguid = substr($Aguid, $made);
                $Bguid = substr($Bguid, $made);
                $Cguid = substr($Cguid, $made);
            } else {
                $made = strpos($Cguid, "N");
                if ($made === false) {
                    $made = strlen($Cguid);
                }
                if ($made % 2 == 1) {
                    $made--;
                }
                if ($made == 0) {
                    $crypt .= chr(($crypt != "") ? $this->JSwap["B"] : $this->JStart["B"]);
                    $crypt .= chr(ord($code[0]) - 32);
                    $code  = substr($code, 1);
                    $Aguid = substr($Aguid, 1);
                    $Bguid = substr($Bguid, 1);
                    $Cguid = substr($Cguid, 1);
                } else {
                    $crypt .= chr(($crypt != "") ? $this->JSwap["C"] : $this->JStart["C"]);
                    for ($j = 0; $j < $made; $j += 2) {
                        $crypt .= chr(intval(substr($code, $j, 2)));
                    }
                    $code  = substr($code, $made);
                    $Aguid = substr($Aguid, $made);
                    $Bguid = substr($Bguid, $made);
                    $Cguid = substr($Cguid, $made);
                }
            }
        }

        $check = ord($crypt[0]);
        for ($i = 0; $i < strlen($crypt); $i++) {
            $check += (ord($crypt[$i]) * $i);
        }
        $check %= 103;
        $crypt .= chr($check) . chr(106) . chr(107);

        // Calcular el número de módulos
        $modTotal = 0;
        for ($i = 0; $i < strlen($crypt); $i++) {
            $c = ord($crypt[$i]);
            if (isset($this->T128[$c])) {
                foreach ($this->T128[$c] as $val) {
                    $modTotal += $val;
                }
            }
        }

        $modWidth = ($modTotal > 0) ? ($w / $modTotal) : 0.25;

        // Dibujar barras vectoriales (forzar siempre color negro para evitar heredar rellenos previos)
        $this->SetFillColor(0, 0, 0);
        $curX = $x;
        for ($i = 0; $i < strlen($crypt); $i++) {
            $c = ord($crypt[$i]);
            if (isset($this->T128[$c])) {
                $seq = $this->T128[$c];
                for ($j = 0; $j < count($seq); $j++) {
                    $barW = $seq[$j] * $modWidth;
                    if ($j % 2 == 0) {
                        // Barra negra
                        $this->Rect($curX, $y, $barW, $h, 'F');
                    }
                    $curX += $barW;
                }
            }
        }
    }
}
