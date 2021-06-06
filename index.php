<?php
require('vendor/autoload.php');
require_once('simple_html_dom.php');

use \GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Class getTime
 */
class getTime
{
    const WALK_TO_SCHOOL = 5;
    const BUS_RIDE = 8;

    /**
     * @var string
     */

    private $html;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * getTime constructor.
     */
    public function __construct()
    {
        $loader = new FilesystemLoader(__DIR__ . '/template');
        $this->twig = new Environment($loader);
    }

    /**
     * @throws GuzzleException
     */

    private function getHTML()
    {

        $client = new Client([
            'base_uri' => 'https://tokyu.bus-location.jp/blsys/',
        ]);
        $method = 'GET';
        $uri = 'navis?VID=rsl&EID=nt&PRM=&SCT=1&DSMK=2427&DSN=%E4%B8%96%E7%94%B0%E8%B0%B7%E8%AD%A6%E5%AF%9F%E7%BD%B2%E5%89%8D&ASMK=0&ASN=null&FDSN=0&FASN=0&RAMK=1';
        $options = ['verify' => false];
        $response = $client->request($method, $uri, $options);
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
            $this->renderHtml($this->getTimeFromHtml());

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

        } catch (\Twig\Error\LoaderError $e) {
        } catch (\Twig\Error\RuntimeError $e) {
        } catch (\Twig\Error\SyntaxError $e) {
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
                    if (strstr($tmp, "弦巻営行")) {
                        $time_int = (int)str_replace("弦巻営行", "", $tmp);
                    } elseif (strstr($tmp, "終)弦巻営行")) {
                        $time_int = (int)str_replace("終)弦巻営行", "", $tmp);
                    }
                    $departure_time = date("H:i", strtotime($time_int . 'min'));
                    $arrival_time = date("H:i", strtotime(($time_int + self::WALK_TO_SCHOOL + self::BUS_RIDE) . ' min'));
                    $result[] = array("time" => $time_int, "departure" => $departure_time, "arrival" => $arrival_time);
                } elseif
                ($tmp and strstr($tmp, "発車待ち")) {
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

        return $result;
    }
}

//instance
$getTime = new getTime();

//invoke
$getTime();


