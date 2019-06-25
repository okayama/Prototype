# SearchEstraierプラグイン

## 概要

HyperEstraierを利用したサイト内検索機能を提供します。 
また、ユーザーの閲覧履歴やコンテンツデータの属性により関連性の高いコンテンツを自動抽出するAPIを提供します。 

## 設置とインストール

- HyperEstraierをインストールします。
- pluginsディレクトリに HyperEstraierディレクトリを設置します。
- プラグインを有効化し、システムプラグイン設定で estcmdと mecab\(オプション\)を設定します。
- 検索対象のスコープのプラグイン設定で、インデックスのパスを入力し、検索を有効化にチェックを入れます。
- tools/worker\.phpを実行します。
- ビューを作成します。サンプルは plugins/SearchEstraier/theme/views/以下に含まれています。

## 検索フォームと検索結果のビュー

以下のようなビューを作成します。
URLマッピングでアーカイブ種別を「インデックス」とし、ファイル出力を「ダイナミック」とします。 

    <form method="GET" action="<mt:var name="current_archive_url">">
    <div class="form-inline">
      <label> キーワード
      <input type="text" value="<mt:var name="query" escape>" name="query" "></label>
      <label>
        <input type="radio" name="and_or" value="AND" <mt:if name="request.and_or" eq="AND">checked</mt:if>>
        AND
      </label>
      <label>
        <input type="radio" name="and_or" value="OR" <mt:if name="request.and_or" eq="OR">checked</mt:if>>
        AND
      </label>
      <button type="submit">検索</button>
    </div>
    </form>
    <mt:var name="request.query" setvar="query">
    <mt:estraiersearch phrase="$query" prefix="estraier_" and_or="AND" default_limit="10" workspace_ids="0,1">
      <mt:if name="__first__">
        <p>
        「<mt:var name="query" escape>」の検索結果( <mt:var name="search_hit">件ヒットしました )
        </p>
        <ul>
      </mt:if>
      <li>
        <a href="<mt:var name="estraier_uri" escape>"><strong><mt:var name="estraier_title" escape></strong></a>
        <p><mt:var name="estraier_snippet"></p>
      </li>
      <mt:if name="__last__">
        </ul>
      </mt:if>
    </mt:estraiersearch>
    <mt:if name="query">
    <mt:unless name="search_hit">
    <p>「<mt:var name="query" escape>」にマッチするページはありませんでした。</p>
    </mt:unless>
    </mt:if>

## ページネーションのビュー

    <mt:if name="estraier_pagertotal" gt="1">
    <mt:for from="1" to="$estraier_pagertotal">
    <mt:if name="__first__">
    <nav aria-label="ページネーション">
      <ul>
        <li>
          <a aria-label="先頭へ" href="<mt:var name="current_archive_url">?query=<mt:var name="query" escape encode_url="1"><mt:if name="request.and_or">&amp;and_or=<mt:var name="request.and_or" escape></mt:if>">
            &laquo;
          </a>
        </li>
        <mt:if name="request.offset">
          <a aria-label="前へ" href="<mt:var name="current_archive_url">?query=<mt:var name="query" escape encode_url="1">&amp;offset=<mt:var name="estraier_prevoffset"><mt:if name="request.and_or">&amp;and_or=<mt:var name="request.and_or" escape></mt:if>">
            &lsaquo;
          </a>
        </mt:if>
    </mt:if>
        <li class="<mt:if name="__value__" eq="$estraier_currentpage"> active</mt:if>">
        <a href="<mt:var name="current_archive_url">?query=<mt:var name="query" escape encode_url="1">&amp;offset=<mt:math eq="x * y" x="$__index__" y="$estraier_limit"><mt:if name="request.and_or">&amp;and_or=<mt:var name="request.and_or" escape></mt:if>"><mt:var name="__value__"></a>
        </li>
    <mt:if name="__last__">
        <mt:if name="estraier_nextoffset">
          <a aria-label="次へ" href="<mt:var name="current_archive_url">?query=<mt:var name="query" escape encode_url="1">&amp;offset=<mt:var name="estraier_nextoffset"><mt:if name="request.and_or">&amp;and_or=<mt:var name="request.and_or" escape></mt:if>">
            &rsaquo;
          </a>
        </mt:if>
        <li>
          <a aria-label="最後へ" href="<mt:var name="current_archive_url">?query=<mt:var name="query" escape encode_url="1">&amp;offset=<mt:math eq="( x - 1 ) * y" x="$estraier_pagertotal" y="$estraier_limit">">
            &raquo;
          </a>
        </li>
      </ul>
    </nav>
    </mt:if>

## レコメンドAPIアプリケーション

URLをAPIに渡すことで、関連性の高い文書を表示させたり、ユーザーの興味・関心にマッチする文書をレコメンドできます。

    http://example.com/powercmsx/plugins/SearchEstraier/app/pt-recommend-api.php

必要に応じて別の場所に移動してください。

### パラメタ

- type=interest \(このパラメタを付けるとユーザーの閲覧履歴からお勧めするページのリストを返します。パラメタのないときはページの関連性で検索します\)
- workspace\_ids=0,1 \(検索対象とするworkspace\_id をカンマ区切りで指定します\)
- workspace\_id=0 \(検索対象とするworkspace\_id が一つの時、数字を指定します\)
- limit=5 \(検索する件数を指定します\)

    pt-recommend-api.php?url=http%3A%2F%2Fexample.com%2Fpage.html&workspace_ids=0,1&type=interest&limit=5
    

### サンプル・レスポンス\(JSON形式\)

    [{"snippet":"PowerCMS X | フォームの作成 「フォーム」モデルは、フォームの作成、投稿(コンタクト)の受付、メールによる通知などを管理します。ヘッダメニューの「コミュニケーションアイコン コニュニケーション」から各オブジェクトにアクセスできます。 モデル 説明 フォーム 「設問」をグループ化したものがフォームに... ","uri":"http:\/\/mt4local.alfasado.net\/powercmsx\/site\/about_powercms_x\/form.html","digest":"e4cab12455f0b6b7c00ab11d1eff0e08","mdate":"2019-06-25T05:22:01Z","metadata":"About PowerCMS X,@documentation","mime_type":"text\/html","model":"page","object_id":"108","tags":"@documentation","title":"フォームの作成","viewport":"width=device-width, initial-scale=1","workspace_id":"0"}]
