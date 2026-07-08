<?php
namespace app\api\controller;

class Signplist extends \app\common\controller\Api
{
	protected $noNeedLogin = ["*"];
	protected $noNeedRight = ["*"];


    public function Index()
	{
		$file = time();
		
		$appname=$_GET['appname'];
		$appid=$_GET['appid'];
		$ipaurl=$_GET['ipaurl'];
		$filesPath = $_SERVER["DOCUMENT_ROOT"] . "/temp/" . $file.".plist";
		$app_version = '1.0';
		$plist_content = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>items</key>
    <array>
        <dict>
            <key>assets</key>
            <array>
                <dict>
                    <key>kind</key>
                    <string>software-package</string>
                    <key>url</key>
                    <string>$ipaurl</string>
                </dict>
            </array>
            <key>metadata</key>
            <dict>
                <key>bundle-identifier</key>
                <string>$appid</string>
                <key>bundle-version</key>
                <string>$app_version</string>
                <key>kind</key>
                <string>software</string>
                <key>title</key>
                <string>$appname</string>
            </dict>
        </dict>
    </array>
</dict>
</plist>
EOD;

// 将plist内容写入文件

$plist_file = fopen($filesPath, 'w');
fwrite($plist_file, $plist_content);
fclose($plist_file);
		
		$domain = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["SERVER_NAME"];
		$plisturl = $domain . "/temp/" . $file . ".plist";
		$data=[
		    'code' => 1, 
		    'msg' => '请求成功', 
		    'data' => [
		        'plist' => $plisturl
		        ]
		];
		
		return json($data);
	}
}