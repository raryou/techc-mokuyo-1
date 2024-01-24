<?php
// データベースに接続
$dbh = new PDO('mysql:host=mysql;dbname=techc', 'root', '');
session_start();

// ログインしていない場合はログインページにリダイレクト
if (empty($_SESSION['login_user_id'])) {
    header("HTTP/1.1 302 Found");
    header("Location: /login.php");
    return;
}

// 現在のログインユーザー情報を取得する
$user_select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$user_select_sth->execute([':id' => $_SESSION['login_user_id']]);
$user = $user_select_sth->fetch();

// ページ数の取得、デフォルトは1
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// 1ページあたりの表示件数
$perPage = 10;

// オフセットの計算
$offset = ($page - 1) * $perPage;

// クエリの組み立て（LIMITおよびOFFSETを追加してページネーション）
$sql = 'SELECT bbs_entries.*, users.name AS user_name, users.icon_filename AS user_icon_filename'
    . ' FROM bbs_entries'
    . ' INNER JOIN users ON bbs_entries.user_id = users.id'
    . ' WHERE'
    . '   bbs_entries.user_id IN'
    . '     (SELECT followee_user_id FROM user_relationships WHERE follower_user_id = :login_user_id)'
    . '   OR bbs_entries.user_id = :login_user_id'
    . ' ORDER BY bbs_entries.created_at DESC'
    . ' LIMIT :perPage OFFSET :offset'; // LIMITおよびOFFSETを追加してページネーション

$select_sth = $dbh->prepare($sql);
$select_sth->bindParam(':login_user_id', $_SESSION['login_user_id'], PDO::PARAM_INT);
$select_sth->bindParam(':perPage', $perPage, PDO::PARAM_INT);
$select_sth->bindParam(':offset', $offset, PDO::PARAM_INT);
$select_sth->execute();

// 投稿処理
if (isset($_POST['body']) && !empty($_SESSION['login_user_id'])) {
    $image_filename = null;

    // 画像が送信された場合は保存
    if (!empty($_POST['image_base64'])) {
        // data:~base64, の部分を削除
        $base64 = preg_replace('/^data:.+base64,/', '', $_POST['image_base64']);
        
        // base64からバイナリにデコード
        $image_binary = base64_decode($base64);
        
        // 新しいファイル名を作成してバイナリを保存
        $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.png';
        $filepath = '/var/www/upload/image/' . $image_filename;
        file_put_contents($filepath, $image_binary);
    }

    // データベースに挿入
    $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (user_id, body, image_filename) VALUES (:user_id, :body, :image_filename)");
    $insert_sth->execute([
        ':user_id' => $_SESSION['login_user_id'],
        ':body' => $_POST['body'],
        ':image_filename' => $image_filename,
    ]);

    // 処理が完了したらリダイレクト
    // リダイレクトしないと、リロード時に同じ内容で再度POSTされる可能性があります
    header("HTTP/1.1 302 Found");
    header("Location: ./timeline.php");
    return;
}
?>
<div>
    現在 <?= htmlspecialchars($user['name']) ?> (ID: <?= $user['id'] ?>) さんでログイン中
</div>
<div style="margin-bottom: 1em;">
    <a href="/setting/index.php">設定画面</a>
    /
    <a href="/users.php">会員一覧画面</a>
</div>
<!-- フォームのPOST先はこのファイル自身にする -->
<form method="POST" action="./timeline.php"><!-- enctypeは外しておきましょう -->
    <textarea name="body" required></textarea>
    <div style="margin: 1em 0;">
        <input type="file" accept="image/*" name="image" id="imageInput">
    </div>
    <input id="imageBase64Input" type="hidden" name="image_base64"><!-- base64を送る用のinput (非表示) -->
    <canvas id="imageCanvas" style="display: none;"></canvas><!-- 画像縮小に使うcanvas (非表示) -->
    <button type="submit">送信</button>
</form>
<hr>
<dl id="entryTemplate" style="display: none; margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
    <dt>番号</dt>
    <dd data-role="entryIdArea"></dd>
    <dt>投稿者</dt>
    <dd>
        <a href="" data-role="entryUserAnchor"></a>
        <a href="" data-role="entryUserAnchor">
            <img data-role="entryUserIconImage"
                style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">
            <span data-role="entryUserNameArea"></span>
        </a>
    </dd>
    <dt>日時</dt>
    <dd data-role="entryCreatedAtArea"></dd>
    <dt>内容</dt>
    <dd data-role="entryBodyArea">
    </dd>
</dl>
<div id="entriesRenderArea"></div>
<script>
document.addEventListener("DOMContentLoaded", () => {
    const entryTemplate = document.getElementById('entryTemplate');
    const entriesRenderArea = document.getElementById('entriesRenderArea');
    let currentPage = 1; // 現在のページ数

    function loadMoreEntries() {
        const request = new XMLHttpRequest();
        request.onload = (event) => {
            const response = event.target.response;
            response.entries.forEach((entry) => {
                // ... (以前のレンダリングロジック、既存のコードを参照)
                entriesRenderArea.appendChild(entryCopied);
            });
        };

        request.open('GET', `/timeline_json.php?page=${currentPage}`, true);
        request.responseType = 'json';
        request.send();
        currentPage++; // 次のページを読み込む
    }

    // 最初に1ページ目のコンテンツを読み込む
    loadMoreEntries();

    // スクロールイベントを監視
    window.addEventListener('scroll', () => {
        if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 500) {
            // ページの最下部にスクロールしたら、さらにコンテンツを読み込む
            loadMoreEntries();
        }
    });

    const request = new XMLHttpRequest();
    request.onload = (event) => {
        const response = event.target.response;
        response.entries.forEach((entry) => {
            // テンプレートから要素をコピー
            const entryCopied = entryTemplate.cloneNode(true);
            // display: none を display: block に変更
            entryCopied.style.display = 'block';
            // id属性を設定 (レスアンカ用)
            entryCopied.id = 'entry' + entry.id.toString();
            // 番号(ID)を表示
            entryCopied.querySelector('[data-role="entryIdArea"]').innerText = entry.id.toString();

            // アイコン画像が存在する場合は表示、存在しない場合はimg要素を非表示に
            if (entry.user_icon_file_url !== undefined) {
                entryCopied.querySelector('[data-role="entryUserIconImage"]').src = entry.user_icon_file_url;
            } else {
                entryCopied.querySelector('[data-role="entryUserIconImage"]').style.display = 'none';
            }

            // 名前を表示
            entryCopied.querySelector('[data-role="entryUserNameArea"]').innerText = entry.user_name;

            // 名前のリンク先（プロフィール）のURLを設定
            entryCopied.querySelector('[data-role="entryUserAnchor"]').href = entry.user_profile_url;
            // 投稿日時を表示
            entryCopied.querySelector('[data-role="entryCreatedAtArea"]').innerText = entry.created_at;
            // 本文を表示 (ここはHTMLなのでinnerHTMLを使用)
            entryCopied.querySelector('[data-role="entryBodyArea"]').innerHTML = entry.body;
            // 画像が存在する場合に本文の下部に画像を表示
            if (entry.image_file_url !== undefined) {
                const imageElement = new Image();
                imageElement.src = entry.image_file_url; // 画像URLを設定
                imageElement.style.display = 'block'; // img要素はデフォルトでインライン要素なのでブロック要素に変更
                imageElement.style.marginTop = '1em'; // 上部の余白を設定
                imageElement.style.maxHeight = '300px'; // 表示する最大サイズ（縦）を設定
                imageElement.style.maxWidth = '300px'; // 表示する最大サイズ（横）を設定
                entryCopied.querySelector('[data-role="entryBodyArea"]').appendChild(imageElement); // 本文エリアに画像を追加
            }
            // 最後に実際の描画を行う
            entriesRenderArea.appendChild(entryCopied);
        });
    }
    request.open('GET', '/timeline_json.php', true); // timeline_json.php を叩く
    request.responseType = 'json';
    request.send();
    // 以下は画像縮小用
    const imageInput = document.getElementById("imageInput");
    imageInput.addEventListener("change", () => {
        if (imageInput.files.length < 1) {
            // 未選択の場合はスキップ
            return;
        }
        const file = imageInput.files[0];
        if (!file.type.startsWith('image/')) { // 画像でない場合はスキップ
            return;
        }
        // 画像縮小処理
        const imageBase64Input = document.getElementById("imageBase64Input"); // base64を送る用のinput
        const canvas = document.getElementById("imageCanvas"); // 描画するcanvas
        const reader = new FileReader();
        const image = new Image();
        reader.onload = () => { // ファイルの読み込み完了時の処理
            image.onload = () => { // 画像として読み込み完了時の処理
                // 元の縦横比を保ちつつ、縮小するサイズを計算してcanvasの縦横に指定
                const originalWidth = image.naturalWidth; // 元画像の横幅
                const originalHeight = image.naturalHeight; // 元画像の高さ
                const maxLength = 1000; // 幅も高さも1000以下に縮小するものとする
                if (originalWidth <= maxLength && originalHeight <= maxLength) { // どちらもmaxLength以下の場合はそのまま
                    canvas.width = originalWidth;
                    canvas.height = originalHeight;
                } else if (originalWidth > originalHeight) { // 横長の場合
                    canvas.width = maxLength;
                    canvas.height = maxLength * originalHeight / originalWidth;
                } else { // 縦長の場合
                    canvas.width = maxLength * originalWidth / originalHeight;
                    canvas.height = maxLength;
                }
                // canvasに実際に画像を描画（canvasはdisplay:noneで非表示）
                const context = canvas.getContext("2d");
                context.drawImage(image, 0, 0, canvas.width, canvas.height);
                // canvasの内容をbase64に変換し、inputのvalueに設定
                imageBase64Input.value = canvas.toDataURL();
            };
            image.src = reader.result;
        };
        reader.readAsDataURL(file);
    });
});
</script>

