# SearchEstraierプラグイン

## 概要

HyperEstraierを利用したサイト内検索機能を提供します。 
また、ユーザーの閲覧履歴やコンテンツデータの属性により関連性の高いコンテンツを自動抽出するAPIを提供します。 

## 設置とインストール

- HyperEstraierをインストールします。
- pluginsディレクトリに HyperEstraierディレクトリを設置します。
- プラグインを有効化し、システムプラグイン設定で estcmdと mecab\(オプション\)のパスを設定します。
- 検索対象のスコープのプラグイン設定で、インデックスのパスを入力し、検索を有効化にチェックを入れます。
- tools/worker\.phpを実行します。
- ビューを作成します。サンプルは plugins/SearchEstraier/theme/views/以下に含まれています。
- ビューに対する URLマッピングでアーカイブ種別を「インデックス」とし、ファイル出力を「ダイナミック」とします。

## 検索フォームと検索結果のビュー

以下のようなビューを作成します。  

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

    <アプリのURL>/plugins/SearchEstraier/app/pt-recommend-api.php

必要に応じて別の場所に移動してください。

- URLをAPIに渡すことで、関連性の高い文書を表示させたり、ユーザーの興味・関心にマッチする文書をレコメンドできます。  
- 閲覧ユーザーの興味・関心の基準となるのは閲覧したページのモデルに関連づいた「タグ」「メタデータ」metaタグ\(keywords\)です。  
- これらのメタデータを数多く指定することによってマッチングの精度を高くすることができます。
- 興味・関心の保存にはクッキーを利用します。
- システム、スペースなどのスコープ毎にクッキーを別名で保存したい時は、プラグイン設定で「クッキーをスコープ固有にする」にチェックを入れてください。

### パラメタ


    pt-recommend-api.php?url=<mt:var name="current_archive_url" escape="url">&workspace_ids=0,1&type=interest&limit=5&model=page

- type=interest \(このパラメタを付けるとユーザーの閲覧履歴からお勧めするページのリストを返します。パラメタのないときはページの関連性で検索します\)
- workspace\_ids=0,1 \(検索対象とするworkspace\_id をカンマ区切りで指定します\)
- workspace\_id=0 \(検索対象とするworkspace\_id が一つの時、数字を指定します\)
- limit=5 \(検索する件数を指定します\)
- model=model\_name \(特定のモデルを指定する時に追加します\)


### サンプル・レスポンス\(JSON形式\)

    [{"snippet":"「フォーム」モデルは、フォームの作成、投稿(コンタクト)の受付、メールによる通知などを管理します。ヘッダメニューの「コミュニケーションアイコン コニュニケーション」から各オブジェクトにアクセスできます。 モデル 説明 フォーム 「設問」をグループ化したものがフォームに... ","uri":"http:\/\/mt4local.alfasado.net\/powercmsx\/site\/about_powercms_x\/form.html","digest":"e4cab12455f0b6b7c00ab11d1eff0e08","mdate":"2019-06-25T05:22:01Z","metadata":"About PowerCMS X,@documentation","mime_type":"text\/html","model":"page","object_id":"108","tags":"@documentation","title":"フォームの作成","viewport":"width=device-width, initial-scale=1","workspace_id":"0"}]

### レスポンスからお勧めページを表示させるビューのサンプル

    <div id="related-list-wrapper" style="display:none">
      <h2>このページと関連性の高いページ</h2>
      <ul id="related-list">
      </ul>
    </div>
    <div id="recommended-list-wrapper" style="display:none">
      <h2>あなたへお勧めのページ</h2>
      <ul id="recommended-list">
      </ul>
    </div>
    <script>
    $(function(){
        $.ajax({
            url: '<mt:property name="path">plugins/SearchEstraier/app/pt-recommend-api.php?limit=5&url=<mt:var name="current_archive_url" escape="url">',
            type: 'GET',
            dataType: 'json',
            timeout: 10000,
            success: function(obj){
                if ( obj.length > 0 ) {
                    for(let k in obj) {
                        var uri = obj[k].uri;
                        var title = obj[k].title;
                        var tag = $('<a>', { text:title, href: uri });
                        var ptag = $('<li>');
                        ptag.append(tag);
                        $('#related-list').append(ptag);
                        $('#related-list-wrapper').show();
                    }
                }
            },
            error: function(){
            }
        });
        $.ajax({
            url: '<mt:property name="path">plugins/SearchEstraier/app/pt-recommend-api.php?type=interest&limit=5&url=<mt:var name="current_archive_url" escape="url">',
            type: 'GET',
            dataType: 'json',
            timeout: 10000,
            success: function(obj){
                if ( obj.length > 0 ) {
                    for(let k in obj) {
                        var uri = obj[k].uri;
                        var title = obj[k].title;
                        var tag = $('<a>', { text:title, href: uri });
                        var ptag = $('<li>');
                        ptag.append(tag);
                        $('#recommended-list').append(ptag);
                        $('#recommended-list-wrapper').show();
                    }
                }
            },
            error: function(){
            }
        });
    });
    </script>
