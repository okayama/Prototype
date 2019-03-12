#!/bin/bash

#set -u

option="${1}"
if [ "${option}" == 'clean' ]; then
    rm -rf src zip
    exit
fi

basename='PowerCMSX'
version='1.00'

_adv="${basename}"-"${version}"

mkdir -p src/"${_adv}"
mkdir -p zip

cp -a powercmsx src/

rm src/powercmsx/docs/PowerCMSX.docx.zip
rm src/powercmsx/docs/PowerCMSX.pdf
rm -rf src/powercmsx/plugins/NORENImporter
find src -type f -name ".git*" | xargs rm -rf

cd src
mv powercmsx "${_adv}"/
zip -qr "${_adv}".zip "${_adv}"/

mv ./"${_adv}".zip ../zip/

exit
