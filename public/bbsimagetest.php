<?php
$dbh = new PDO('mysql:host=mysql;dbname=techc', 'root', '');
// PDOを作成する　MySQLデータベースに接続します　データベースのホスト名は「mysql」、データベース名は「techc」、ユーザー名は「root」パスワードは空です
$image_filename = null;
if (isset($_POST['body'])) {
  // POSTリクエストを通じて「body」という名前のパラメータが送信されたかどうかをチェックし、ユーザーがテキストコンテンツを送信したかどうかを判断します
  $image_filename = null;
  if (isset($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {
    // 「image」という名前のファイルアップロードフィールドを通じてファイルがアップロードされたかどうかをチェックし、アップロードされたファイルが空でないかも確認します
    if (preg_match('/^image\//', mime_content_type($_FILES['image']['tmp_name'])) !== 1) {
      header("HTTP/1.1 302 Found");
      header("Location: ./bbsimagetest.php");
    }
    // mime_content_type()関数を使用してアップロードされたファイルが画像のタイプであるかどうかを確認します。画像でない場合、302リダイレクトヘッダーを送信し、「bbsimagetest.php」ページにユーザーをリダイレクトします
    $pathinfo = pathinfo($_FILES['image']['name']);
    $extension = $pathinfo['extension'];
    // アップロードされたファイルの元のファイル名を取得し、pathinfo()関数を使用してファイルの拡張子を解析します
    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.' . $extension;
    $filepath =  '/var/www/upload/image/' . $image_filename;
    move_uploaded_file($_FILES['image']['tmp_name'], $filepath);
  }
  // 新しい一意のファイル名を生成し、時間スタンプ、ランダムバイト、拡張子を組み合わせます。その後、アップロードされたファイルをサーバー上の指定されたパスに移動します
  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (body, image_filename) VALUES (:body, :image_filename)");
  $insert_sth->execute([
    ':body' => $_POST['body'],
    ':image_filename' => $image_filename,
  ]);
  // プリペアドステートメントを使用してユーザーが送信したテキストコンテンツと画像ファイル名をbbs_entriesテーブルに挿入します
  header("HTTP/1.1 302 Found");
  header("Location: ./bbsimagetest.php");
  return;
}
// HTTP 302リダイレクトヘッダーを送信し、ユーザーを「bbsimagetest.php」ページにリダイレクトして、同じコンテンツを繰り返し送信するのを防ぎます
$select_sth = $dbh->prepare('SELECT * FROM bbs_entries ORDER BY created_at DESC');
$select_sth->execute();
// プリペアドステートメントを使用してbbs_entriesテーブルからデータを取得し、作成日時の降順で結果を取得します
?>
<head>
  <title>画像投稿できる掲示板</title>
</head>

<form method="POST" action="./bbsimagetest.php" enctype="multipart/form-data"> 
  <textarea name="body"></textarea>
  <div style="margin: 1em 0;">
    <input type="file" accept="image/*" name="image">
    <input type="file" accept="image/*" name="image" id="imageInput">
  </div>
  <button type="submit">送信</button>
</form>
<hr>
<?php foreach($select_sth as $entry): ?>
  <dl style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
    <dt>ID</dt>
    <dd><?= $entry['id'] ?></dd>
    <dt>日時</dt>
    <dd><?= $entry['created_at'] ?></dd>
    <dt>内容</dt>
    <dd>
      <?= nl2br(htmlspecialchars($entry['body'])) // 必ず htmlspecialchars() すること ?>
      <?php if(!empty($entry['image_filename'])): // 画像がある場合は img 要素を使って表示 ?>
      <div>
        <img src="/image/<?= $entry['image_filename'] ?>" style="max-height: 10em;">
      </div>
      <?php endif; ?>
    </dd>
  </dl>
<?php endforeach ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const imageInput = document.getElementById("imageInput");
  imageInput.addEventListener("change", () => {
    if (imageInput.files.length < 1) {
      // 未選択の場合
      return;
    }
    if (imageInput.files[0].size > 5 * 1024 * 1024) {
      alert("5MB以下のファイルを選択してください。");
      imageInput.value = "";
    }
// ファイルのアップロードフィールドの変更イベントを監視し、選択したファイルのサイズが5MBを超える場合、警告を表示してファイル選択をクリアします
  });
});
</script>
