<?php
// MySQL データベースへの接続
$dbh = new PDO('mysql:host=mysql;dbname=techc', 'root', '');

// セッションの開始
session_start();

// ログインしていない場合はエラーを返す
if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 401 Unauthorized"); // 未認証のステータスコード
  header("Content-Type: application/json");
  print(json_encode(['entries' => []])); // 空のエントリを返す
  return;
}

// 現在のログインユーザー情報を取得
$user_select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$user_select_sth->execute([':id' => $_SESSION['login_user_id']]);
$user = $user_select_sth->fetch();

// 投稿データの取得
$sql = 'SELECT bbs_entries.*, users.name AS user_name, users.icon_filename AS user_icon_filename'
  . ' FROM bbs_entries'
  . ' INNER JOIN users ON bbs_entries.user_id = users.id'
  . ' WHERE'
  . '   bbs_entries.user_id IN'
  . '     (SELECT followee_user_id FROM user_relationships WHERE follower_user_id = :login_user_id)'
  . '   OR bbs_entries.user_id = :login_user_id'
  . ' ORDER BY bbs_entries.created_at DESC';
$select_sth = $dbh->prepare($sql);
$select_sth->execute([
  ':login_user_id' => $_SESSION['login_user_id'],
]);

// HTML body の出力用の関数を定義
function bodyFilter (string $body): string
{
  $body = htmlspecialchars($body); // エスケープ処理
  $body = nl2br($body); // 改行文字を<br>要素に変換

  // >>1 といった文字列を該当番号の投稿へのページ内リンクとする (レスアンカー機能)
  // 「>」(半角の大なり記号)は htmlspecialchars() でエスケープされているため注意
  $body = preg_replace('/&gt;&gt;(\d+)/', '<a href="#entry$1">&gt;&gt;$1</a>', $body);

  return $body;
}

// JSON 形式で返すエントリのデータ
$result_entries = [];
foreach ($select_sth as $entry) {
  $result_entry = [
    'id' => $entry['id'],
    'user_name' => $entry['user_name'],
    'user_icon_file_url' => empty($entry['user_icon_filename']) ? '' : ('/image/' . $entry['user_icon_filename']),
    'user_profile_url' => '/profile.php?user_id=' . $entry['user_id'],
    'body' => bodyFilter($entry['body']),
    'created_at' => $entry['created_at'],
  ];
  $result_entries[] = $result_entry;
}

// 正常なステータスコードを返す
header("HTTP/1.1 200 OK");
header("Content-Type: application/json");
print(json_encode(['entries' => $result_entries]));
