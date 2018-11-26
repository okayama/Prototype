# make.sh

## 設定

package.sh 中の下記の変数でパッケージ名に含まれるバージョンを決定します。

    basename='PowerCMSX'
    version='1.00'

    # PowerCMSX-1.00.zip
    _adv="${basename}"-"${version}"

## 作業ディレクトリを掃除する

    $ sh package.sh clean

clean オプションをつけて実行すると `src` と `zip` ディレクトリが削除されます

## パッケージを作成する

パッケージの作成では次のディレクトリを利用します。

- `src` : パッケージ前のファイル/ディレクトリが生成されるディレクトリ
- `zip` : パッケージが生成されるディレクトリ

### PowerCMS X のパッケージを作成する

    $ bash package.sh
