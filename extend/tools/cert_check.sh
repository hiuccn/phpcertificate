#!/bin/bash
SystemVersion=`uname`

# 安装xmlstarlet：
# sudo yum -y install xmlstarlet
#
# 使用说明：
#p12路径，密码，描述文件路径
# ./wb.sh name.p12 1 name.mobileprovision

Serial=`openssl pkcs12 -in "$1" -nodes -passin pass:"$2" | openssl x509 -noout -serial |  grep -oP 'serial=\K[0-9A-Fa-f]+'`

if [ "$Serial" = "" ] ; then
	echo "<br>P12错误<br>"
	exit
else

	echo "P12证书序列号:$Serial""wb<br>"
fi


CertificationName=`openssl pkcs12 -in "$1" -nodes -passin pass:"$2" | openssl x509 -noout -subject | \
sed 's/\(.*\)\/CN=\(.*\)\/OU\(.*\)/\2/g'`
echo "P12证书名称:$CertificationName""wb<br>"
#打印证书有效期
EndDate=`openssl pkcs12 -in "$1" -nodes -passin pass:"$2" | openssl x509 -noout -enddate | cut -b 10-`
LocalEndDateString=`date -d "$EndDate" +"%Y-%m-%d %T"`
LocalEndDate=`date -d "$EndDate" +"%s"`
echo "P12证书有效期:$LocalEndDateString""wb<br>"


NowDate=`date +"%s"`
if [[ $NowDate -gt $LocalEndDate ]]; then
	echo "该P12证书已过期<br>"
else
	echo "该P12证书未到期<br>"

fi


ProvisionUUID=`openssl smime -inform der -verify -noverify -in "$3" | \
xmlstarlet sel -t -v "/plist/dict/key[. = 'UUID']/following-sibling::string[1]"`
echo "<br>描述文件UUID=$ProvisionUUID""wb<br>"

ProvisionSerial=`openssl smime -inform der -verify -noverify -in "$3" | \
xmlstarlet sel -t -v "/plist/dict/key[. = 'DeveloperCertificates']/following-sibling::array[1]/data[1]" | \
awk '{print $1}' | sed '/^$/d' | base64 -d | openssl x509 -serial -inform der | head -n 1 | \
grep -oP 'serial=\K[0-9A-Fa-f]+'`
echo "<br>描述文件对应的证书序列号:$ProvisionSerial<br>"

if [ "$Serial" = "$ProvisionSerial" ] ; then

	echo "P12证书和描述文件对应的证书一致。<br>"
else

	echo "P12证书和描述文件对应的证书不一致！<br>"
fi