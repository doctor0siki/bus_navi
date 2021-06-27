<?php
require('vendor/autoload.php');
require_once('simple_html_dom.php');

use \GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

/**
 * Class getTime
 */
class getTime
{
    /**
     * バスナビのベースURL
     */

    const BASE_URL = 'https://tokyu.bus-location.jp/blsys/';

    /**
     * WALK_TO_BUS_STOP　自宅最寄りバス停までの徒歩時間
     */
    const WALK_TO_BUS_STOP = 6;

    /**
     * WALK_TO_SCHOOL　バス下車後学校までの徒歩時間
     */
    const WALK_TO_SCHOOL = 5;

    /**
     * BUS_RIDE バス乗車時間
     */
    const BUS_RIDE = 8;

    /**
     *　到着時刻がこの時刻になったら遅刻という文字を追記 ex 8:10 →　810
     */
    const TIME_LIMIT = 815;

    /**
     * 終点の名前をセットする
     */
    const LAST_BUS_STOP = "弦巻営";

    /**
     * @var string
     */
    private $html;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var bool
     */
    private $render;

    /**
     * @var string[]
     */
    private $params;

    /**
     * @var string
     */
    private $uri;

    /**
     * getTime constructor.
     * @param bool $render
     */
    public function __construct($render = true)
    {
        $loader = new FilesystemLoader(__DIR__ . '/template');
        $this->twig = new Environment($loader);
        $this->render = $render;
        $this->params = ["VID" => "rsl",
            "EID" => "nt",
            "PRM" => "",
            "SCT" => "1",
            "DSMK" => "2427",
            "ASMK" => "0",
            "ASN" => "null",
            "FDSN" => "0",
            "FASN" => "0",
            "RAMK" => "1"];
    }

    /**
     * @throws GuzzleException
     */

    private function getHTML()
    {
        $client = new Client([
            'base_uri' => self::BASE_URL,
        ]);
        $method = 'GET';
        $url = "navis?" . http_build_query($this->params);
        $options = ['verify' => false];
        $response = $client->request($method, $url, $options);
        $this->uri = self::BASE_URL . $url;
        $this->html = $response->getBody()->getContents();
    }

    /**
     * 実行
     */

    public function __invoke()
    {
        try {

            //get html
            $this->getHTML();
            //get time
            $result = $this->getTimeFromHtml();
            if ($this->render) {
                $this->renderHtml($result);
            } else {
                echo json_encode($result);
            }

        } catch (Exception $e) {
            print "なにか問題発生" . $e->getMessage();

        } catch (GuzzleException $e) {

            print "なにか問題発生" . $e->getMessage();
        }
    }

    /**
     * @param array $bus_info
     */

    private function renderHtml(array $bus_info)
    {
        try {

            $data["bus_info"] = $bus_info;
            echo $this->twig->render('index.html.twig', $data);

        } catch (LoaderError $e) {
        } catch (RuntimeError $e) {
        } catch (SyntaxError $e) {
        }
    }

    /**
     * @return array
     */

    private function getTimeFromHtml(): array
    {
        $result = [];

        // HTMLをオブジェクト化
        $html_obj = str_get_html($this->html);

        // 下記の記述方法と同じ
        foreach ($html_obj->find("[class=waittm]") as $val) {
            $tmp = strip_tags($val);
            if ($tmp) {
                //残り時間を計測
                $time_int = (int)str_replace("分待ち", "", $tmp);
                //結果を生成

                $departure_time = date("H:i", strtotime($time_int . 'min'));
                $arrival_time = date("H:i", strtotime(($time_int + self::WALK_TO_SCHOOL + self::BUS_RIDE) . ' min'));

                $result[] = array("time" => $time_int, "departure" => $departure_time, "arrival" => $arrival_time);
            }
        }
        //↑で取れなかったら
        if (!count($result)) {
            //発車待ちも見てみる
            foreach ($html_obj->find("[class=label-d]") as $val) {
                $tmp = strip_tags($val);
                if ($tmp and strstr($tmp, "分待ち")) {
                    //残り時間を計測
                    if (strstr($tmp, self::LAST_BUS_STOP . "行")) {
                        $time_int = (int)str_replace(self::LAST_BUS_STOP . "行", "", $tmp);
                    } elseif (strstr($tmp, "終)" . self::LAST_BUS_STOP . "行")) {
                        $time_int = (int)str_replace("終)" . self::LAST_BUS_STOP . "行", "", $tmp);
                    }
                    $departure_time = date("H:i", strtotime($time_int . 'min'));
                    $arrival_time = date("H:i", strtotime(($time_int + self::WALK_TO_SCHOOL + self::BUS_RIDE) . ' min'));
                    $result[] = array("time" => $time_int, "departure" => $departure_time, "arrival" => $arrival_time);
                } elseif ($tmp and strstr($tmp, "発車待ち")) {
                    //残り時間は15分
                    $time_int = 15;
                    $departure_time = date("H:i", strtotime($time_int . 'min'));
                    $arrival_time = date("H:i", strtotime(($time_int + self::WALK_TO_SCHOOL + self::BUS_RIDE) . ' min'));
                    $result[] = array("time" => $time_int, "departure" => $departure_time, "arrival" => $arrival_time);
                }
            }
        }

        //昇順ソートする
        sort($result);

        //残り時間によってメッセージを切り替える
        foreach ($result as $key => $value) {
            switch (true) {
                case $value["time"] <= self::WALK_TO_BUS_STOP:
                    $result[$key]["text"] = "次のバスを待ちましょう";
                    break;
                case $value["time"] <= self::WALK_TO_BUS_STOP + 3:
                    $result[$key]["text"] = "そろそろ家を出ましょう";
                    break;
                case $value["time"] <= self::WALK_TO_BUS_STOP + 8:
                    $result[$key]["text"] = "PASMO/マスク/ハンカチ/ティッシュ/水筒/健康観察表";
                    break;
                default:
                    $result[$key]["text"] = "-";
                    break;
            }

            //8:15過ぎているなら遅刻
            if ((int)str_replace(":", "", $value["arrival"]) > self::TIME_LIMIT) {
                $result[$key]["text"] = "<b style='color: red'>【遅刻】</b>" . $result[$key]["text"];
            }
        }

        //バスなびURLをセット
        $result["uri"] = $this->uri;

        return $result;
    }
}

//最初はレンダリングする
$render = true;

//API経由の場合はレンダリングしない
if ($_GET["api"] == 1) {
    $render = false;
}

//instance
$getTime = new getTime($render);

//invoke
$getTime();


