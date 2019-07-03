# Mirroring プラグイン

## 概要

LFTPを利用してウェブサイトをミラーリングします。スコープ毎に最大5箇所までのミラーリングが可能です。

## インストールと設定

- lftpがインストールされていない場合、まず lftpをインストールしてください。
- PHPから lftpが実行できることを確認してください。
- lftpのパスが「/usr/local/bin/lftp」とは異なる場合、環境変数「mirroring\_lftp\_path」に lftpコマンドのパスを記述してください。
- プラグインを有効化します。
- システム、各スペースのプラグイン設定で、同期先の設定を行ってください。最大5箇所まで指定できます。
- 複数台のサーバーへの同期を設定する場合、環境変数「mirroring\_servers」に 2〜5の数値を指定してください。
- 同期が実行可能なのは各スコープの「ミラーリング」権限を持つユーザーです。

- <a class="btn btn-secondary" href="<mt:var name="script_uri">?__mode=mirroring_lftp_test" target="_blank"><mt:trans phrase="LFTP Test" component="Mirroring"> <i class="fa fa-external-link" aria-hidden="true"></i></a>

## 全体の設定

    - ステージング環境のパス
      ローカルディスク上の一時ディレクトリに同期してからリモートサーバーへ同期する場合に指定してください。
    ※ 指定可能なパス(前方一致)を環境変数「mirroring_staging_root_path」にカンマ区切りで指定してください。
      mirroring_staging_root_path の指定値がない時、この項目は表示されません。

    実行されるコマンドの例 (rsyncが有効な場合) :
    
    /usr/bin/rsync -rvtu --delete --exclude='.*' --exclude='/powercmsx/' --exclude='/assets/' /var/www/html/ /var/www/html2

    ※ rsync が有効でない場合、cpコマンドまたは PHPの copy関数(unlink関数)を利用してディレクトリの同期を行います。
    ※ 「削除の同期」「隠しファイル」「除外条件」についての設定は「同期先の設定(1)」が使われます。

## 転送先ごとの設定の例

    - プロトコル (sftpもしくは ftpから選択)
    - ポート (ポート番号)
    - ログイン名とパスワード(例 : pcmsxuser / **************** )
    - 同期先サーバー(例 : www.powercmsx.jp )
    - リモート・パス (例 : /var/www/htdocs)
    - 削除されたファイルを同期先から削除
    - 隠しファイル('.'から始まるファイル名)を除外する
    - 除外条件 (例:^powercmsx/,^assets/)
    ※ パスワードではなく鍵ファイルを利用する場合、~/.ssh/configを編集してホスト名で sshが可能になるように設定してください。
    
    ~/.ssh/config の設定例 :
    
    Host www.powercmsx.jp
        HostName www.powercmsx.jp
        User pcmsxuser
        Port 22
        IdentityFile 鍵ファイルのパス
    
    実行されるコマンドの例 :
    
    /usr/local/bin/lftp -u pcmsxuser,**************** -p 22 -e 'mirror --verbose=3 --only-newer -R --delete --exclude=^\. --exclude=^powercmsx/ --exclude=^assets/ [サイト・パス] /var/www/htdocs;quit' sftp://www.powercmsx.jp
    
## ミラーリングの予約・デバッグ・実行

- 「ツール」メニューから「ミラーリング」を選択します。
- 「現在の設定を確認」をクリックすると、実行されるコマンドを確認できます。
- 予約する場合は、日付と時刻を指定して「予約する」ボタンをクリックします。複数の予約はできず、再設定すると予約日時は上書きされます。
- 予約されたミラーリングは、設定日時を超えて tools/worker\.php が実行された時に行われます。
- 「実行する」をクリックすると、すぐにミラーリングを実行します。
- 「デバッグする」をクリックすると、ミラーリングは行わず、実行予定のログを返します。
- 「ステージング環境に同期する」をクリックすると、すぐにステージング環境への同期を実行します。
