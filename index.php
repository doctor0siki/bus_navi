<?php
require('vendor/autoload.php');

use \GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;
use Dotenv\Dotenv;

/**
 * Class getTime
 */
class getTime
{

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
    }

    /**
     * @throws GuzzleException
     */

    private function getHTML()
    {
        $client = new Client([
            'base_uri' => $_ENV["BASE_URL"],
        ]);
        $method = 'GET';
        $options = ['verify' => false];
        $response = $client->request($method, "", $options);
        $this->uri = $_ENV["BASE_URL"];
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

        //発車待ちを16分待ちに変更する
        $html_data = str_replace("発車待ち", $_ENV["BUS_DEPARTURE_FROM_TERMINAL_TIME"] . "分待ち", strip_tags($this->html));

        //取得してみる
        preg_match_all("/[0-9]{1,2}分待ち/", $html_data, $matches);

        //ループして数値だけ取る
        foreach ($matches[0] as $val) {
            if ($val) {
                //残り時間を計測
                $time_int = (int)str_replace("分待ち", "", $val);
                //結果を生成
                $departure_time = date("H:i", strtotime($time_int . 'min'));
                $arrival_time = date("H:i", strtotime(($time_int + $_ENV["WALK_TO_SCHOOL"] + $_ENV["BUS_RIDE"]) . ' min'));
                $result[] = array("time" => $time_int, "departure" => $departure_time, "arrival" => $arrival_time);
            }
        }

        //昇順ソートする
        sort($result);

        //残り時間によってメッセージを切り替える
        foreach ($result as $key => $value) {
            switch (true) {

                case $value["time"] <= $_ENV["WALK_TO_BUS_STOP"]:
                    $result[$key]["text"] = $_ENV["WARNING_NEXT_BUS"];
                    break;
                case $value["time"] <= $_ENV["WALK_TO_BUS_STOP"] + 3:
                    $result[$key]["text"] = $_ENV["WARNING_LEAVE_HOME"];
                    break;
                case $value["time"] <= $_ENV["WALK_TO_BUS_STOP"] + 8:
                    $result[$key]["text"] = $_ENV["WARNING_WITH_BELONGINGS"];
                    break;
                default:
                    $result[$key]["text"] = "-";
                    break;
            }

            switch (true) {
                case $value["time"] == 15:
                    $result[$key]["sound"] = "15min.mp3";
                    break;
                case $value["time"] == 10:
                    $result[$key]["sound"] = "10min.mp3";
                    break;
                case $value["time"] == 6:
                    $result[$key]["sound"] = "waitnext.mp3";
                    break;
                default:
                    $result[$key]["sound"] = "";
                    break;
            }

            //8:15過ぎているなら遅刻
            if ((int)str_replace(":", "", $value["arrival"]) > $_ENV["TIME_LIMIT"]) {
                $result[$key]["text"] = "<b style='color: red'>【遅刻】</b>" . $result[$key]["text"];
            }
        }

        //バスなびURLをセット
        $result["uri"] = $this->uri;

        return $result;
    }
}


//.envを読む
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

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


