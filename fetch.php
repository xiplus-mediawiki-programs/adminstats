<?php
require __DIR__ . "/../config/config.php";
if (!in_array(PHP_SAPI, $C["allowsapi"])) {
	exit("No permission");
}

set_time_limit(600);
date_default_timezone_set('UTC');
$starttime = microtime(true);
@include __DIR__ . "/config.php";
require __DIR__ . "/../function/curl.php";
require __DIR__ . "/../function/login.php";
require __DIR__ . "/../function/edittoken.php";

$time = time();
echo "The time now is " . date("Y-m-d H:i:s", $time) . " (UTC)\n";

login();
$edittoken = edittoken();

$starttimestamp = time();
$res = cURL($C["wikiapi"] . "?" . http_build_query(array(
	"action" => "query",
	"prop" => "revisions",
	"format" => "json",
	"rvprop" => "content|timestamp",
	"titles" => $C["page"],
)));
if ($res === false) {
	exit("fetch page fail\n");
}
$res = json_decode($res, true);
$pages = current($res["query"]["pages"]);
$text = $pages["revisions"][0]["*"];
$basetimestamp = $pages["revisions"][0]["timestamp"];

$dates = [
	"-1 year",
	"-6 months",
	"-3 months",
	"-1 month",
];
$counts = [10, 100, 500, 1000];
$nonadmin = ['Jimmy-abot', '滥用过滤器'];
$res = [];
$today = date("Y-m-d");
foreach ($dates as $date) {
	$realdate = date("Y-m-d", strtotime($date));
	echo $realdate . "\n";
	$url = "https://xtools.wmflabs.org/adminstats/zh.wikipedia.org/$today/$realdate?uselang=en";
	for ($i = 0; $i < 3; $i++) {
		$html = file_get_contents($url);
		if ($html !== false) {
			break;
		}
		sleep(3);
	}
	if ($html === false) {
		exit("fetch fail\n");
	}
	preg_match("/<a href='https:\/\/zh\.wikipedia\.org\/w\/index\.php\?title=Special:ListUsers&amp;creationSort=1&amp;group=sysop' target='_blank'>(\d+)<\/a>/", $html, $m);
	$totaladmincount = $m[1] - $C["adminbot"];
	echo "totaladmincount = $totaladmincount\n";
	preg_match_all('/<td class="sort-entry--username" data-value="(.*?)">\s*<a.*<\/a>\s*<\/td>\s*<td class="sort-entry--user-groups".*>\s*(?:<img class="user-group-icon".*\n)*\s*<img class="user-group-icon.*alt="administrator".*\s*<\/td>\s*<td>\s*<a.*\s*&middot;\s*<a.*\s*&middot;\s*<a.*\s*<\/td>\s*<td class="sort-entry--total" data-value="(\d+)"/', $html, $m);
	$admincount = count($m[1]);
	echo "admincount = $admincount\n";
	foreach ($m[2] as $key => $total) {
		$admin = $m[1][$key];
		if (in_array($admin, $nonadmin)) {
			continue;
		}
		foreach ($counts as $count) {
			if ((int) $total < $count) {
				@$res[$count][$date]++;
			}
		}
	}
	$res[1][$date] = $totaladmincount - $admincount;
	echo "\n";
}

$out = "==統計==
*更新日期：<onlyinclude>" . date("Y年n月j日", $time) . " (" . $C["day"][date("w", $time)] . ") " . date("H:i", $time) . " (UTC)</onlyinclude>
*中文維基管理員：" . $totaladmincount . "人
*備註：由[https://xtools.wmflabs.org/adminstats/zh.wikipedia.org XTools]協助統計，只計算刪除、封禁、保護、權限、合併、匯入、防濫用過濾器，因此可能與實際情況有誤差。

===沒有作出管理方面行為的管理員===
*一年：" . $res[1]["-1 year"] . "人（" . round(100 * $res[1]["-1 year"] / $totaladmincount, 1) . "%）
*半年：" . $res[1]["-6 months"] . "人（" . round(100 * $res[1]["-6 months"] / $totaladmincount, 1) . "%）
*三個月：" . $res[1]["-3 months"] . "人（" . round(100 * $res[1]["-3 months"] / $totaladmincount, 1) . "%）
*一個月：" . $res[1]["-1 month"] . "人（" . round(100 * $res[1]["-1 month"] / $totaladmincount, 1) . "%）

===管理方面行為次數少於10次===
*一年：" . $res[10]["-1 year"] . "人（" . round(100 * $res[10]["-1 year"] / $totaladmincount, 1) . "%）
*半年：" . $res[10]["-6 months"] . "人（" . round(100 * $res[10]["-6 months"] / $totaladmincount, 1) . "%）
*三個月：" . $res[10]["-3 months"] . "人（" . round(100 * $res[10]["-3 months"] / $totaladmincount, 1) . "%）
*一個月：" . $res[10]["-1 month"] . "人（" . round(100 * $res[10]["-1 month"] / $totaladmincount, 1) . "%）

===管理方面行為次數少於100次===
*一年：" . $res[100]["-1 year"] . "人（" . round(100 * $res[100]["-1 year"] / $totaladmincount, 1) . "%）
*半年：" . $res[100]["-6 months"] . "人（" . round(100 * $res[100]["-6 months"] / $totaladmincount, 1) . "%）
*三個月：" . $res[100]["-3 months"] . "人（" . round(100 * $res[100]["-3 months"] / $totaladmincount, 1) . "%）

===管理方面行為次數少於500次===
*一年：" . $res[500]["-1 year"] . "人（" . round(100 * $res[500]["-1 year"] / $totaladmincount, 1) . "%）
*半年：" . $res[500]["-6 months"] . "人（" . round(100 * $res[500]["-6 months"] / $totaladmincount, 1) . "%）
*三個月：" . $res[500]["-3 months"] . "人（" . round(100 * $res[500]["-3 months"] / $totaladmincount, 1) . "%）";

$start = strpos($text, $C["text1"]);
$end = strpos($text, $C["text2"]);
$newtext = substr($text, 0, $start) . $out . "\n\n" . substr($text, $end);

echo "---------------\n";
echo $newtext . "\n";
echo "---------------\n";

$summary = $C["summary"];
$post = array(
	"action" => "edit",
	"format" => "json",
	"title" => $C["page"],
	"summary" => $summary,
	"text" => $newtext,
	"token" => $edittoken,
	"starttimestamp" => $starttimestamp,
	"basetimestamp" => $basetimestamp,
);
echo "edit " . $C["page"] . " summary=" . $summary . "\n";

if (!$C["test"]) {
	$res = cURL($C["wikiapi"], $post);
} else {
	$res = false;
	file_put_contents(__DIR__ . "/out.txt", $text);
}
$res = json_decode($res, true);
if (isset($res["error"])) {
	echo "edit fail\n";
	var_dump($res["error"]);
}

$spendtime = (microtime(true) - $starttime);
echo "spend " . $spendtime . " s.\n";
