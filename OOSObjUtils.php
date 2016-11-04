<?php

/**
 * 用于对oos对象（文件）的操作
 *
 * Created by PhpStorm.
 * User: Pang
 * Date: 2016/10/15
 * Time: 16:54
 */
use think\Config;//载入使用TP框架的Config

class OOSObjUtils
{
    private $ossClient;
    private $bucket;
    private $allowIP = ['139.139.139.139'];

    public function __construct($ossClient, $bucket = 'shan-xun')
    {
        $this->initTP($ossClient);
        $this->bucket = $bucket;
    }

    /**
     * 检查是否本地操作
     * 只有在 服务器环境ip才执行删除oss文件操作
     */
    private function checkAllowIP()
    {
        $client_ip = get_client_ip();
        if (in_array($client_ip, $this->allowIP)) {
            return true;
        }
        return false;
    }

    /**
     * 传入'TP'字符传就载入TP的配置文件
     *
     * @param $ossClient
     */
    private function initTP($ossClient)
    {
        if (is_string($ossClient) && $ossClient == 'TP') {//若是任意String 则调用TP框架自带的方法
            $this->ossClient = new \OSS\OssClient(Config::get('OSS_ACCESS_ID'), Config::get('OSS_ACCESS_KEY'), Config::get('OSS_ENDPOINT'), false);
        } else {
            $this->ossClient = $ossClient;
        }
    }

    /**
     * 设置bucket名称
     * @param string $bucket
     */
    public function setBucket($bucket)
    {
        $this->bucket = $bucket;
    }

    /**
     * 删除对象[传入数组则可以批量删除]
     *
     * @param $objName 对象全名
     */
    public function del($objName)
    {
        if ($this->checkAllowIP()) {
            if (is_array($objName)) {
                $action = 'deleteObjects';
            } else {
                $action = 'deleteObject';
            }
            return $this->ossClient->{$action}($this->bucket, $objName);
        }
    }

    /**
     * 返回bucket根目录里面的所有对象信息 [以printR数组形式echo]
     *
     * @param int $count 列出每页的数量
     * @param string $prefix 对象名前缀
     */
    public function listObjects($count = 100, $prefix = '')
    {
        $allObject = $this->ossClient->listObjects($this->bucket, ['max-keys' => $count,
            'prefix' => $prefix]);
        print_r($allObject);
//        return $this->bucket;
    }

    /**
     * 判断bucket里面是否存在 object或者文件夹</br>
     *
     * @param $name
     * @param bool $isDir
     * @return bool
     *
     * 个别时候明明存在的文件夹（里面还有文件）却找不到。
     * 应该是测试时候多次操作导致的问题。最好是手动查看下
     *
     */
    public function has($name)
    {
//        $name = rtrim($name, '/');
//        $name = $isDir ? $name . '/' : $name;
        $doesExist = $this->ossClient->doesObjectExist($this->bucket, $name);
        return $doesExist ? true : false;
    }


    /**
     * 获取对象的Meta信息[以printR数组形式echo]
     * @param $name 对象全名
     * 文件上传时间 $info['_info']['filetime']
     * 文件大小（单位b） $info['_info']['download_content_length'] 或者 $info['content-length']
     * 文件MD5码 $info['etag']
     * 文件类型 $info['content-type']
     */
    public function info($name)
    {
//        $info = $this->ossClient->getObjectMeta($this->bucket, $name);
        print_r($this->getInfo($name));
    }


    private function getInfo($name)
    {
        return $this->ossClient->getObjectMeta($this->bucket, $name);
    }

    /**
     * 获取文件的时间戳
     *
     * @param $name
     * @return mixed
     */
    public function fileTime($name)
    {
        $info = $this->getInfo($name);
        return $info['_info']['filetime'];
    }

    /**
     * 获取文件大小（单位b）
     *
     * @param $name
     * @return mixed
     */
    public function fileSize($name)
    {
        $info = $this->getInfo($name);
        return $info['content-length'];
    }

    /**
     * 获取文件md5
     *
     * @param $name
     * @return mixed
     */
    public function fileMd5($name)
    {
        $info = $this->getInfo($name);
        return trim($info['etag'], '"');
    }

    /**
     * 获取类型
     *
     * @param $name
     * @return mixed
     */
    public function fileType($name)
    {
        $info = $this->getInfo($name);
        return $info['content-type'];
    }


    /**
     * 列出Bucket内指定目录下所有文件和文件夹 ， 根据返回的nextMarker循环得到所有Objects
     *
     * @param string $prefix 指定的目录 默认‘’表示Bucket根目录;dir/;dir/die2;
     * @return array|void
     */
    public function ls($prefix = '')
    {
        $ossClient = $this->ossClient;
        $bucket = $this->bucket;

//    //创建测试文件和文件夹[ dir下的文件和虚拟目录 ]
//    for ($i = 0; $i < 100; $i += 1) {
//        $ossClient->putObject($bucket, "dir/obj" . strval($i), "hi");
//        $ossClient->createObjectDir($bucket, "dir/obj" . strval($i));
//    }

//    $prefix = 'dir/';
        $delimiter = '/';
        $nextMarker = '';
        $maxkeys = 1000;

        $dataFile = [];
        $dataDir = [];
        while (true) {
            $options = array(
                'delimiter' => $delimiter,
                'prefix' => $prefix,
                'max-keys' => $maxkeys,
                'marker' => $nextMarker,
            );
//        print_r($options);
            try {
                $listObjectInfo = $ossClient->listObjects($bucket, $options);
            } catch (OssException $e) {
                printf(__FUNCTION__ . ": FAILED\n");
                printf($e->getMessage() . "\n");
                return;
            }
            // 得到nextMarker，从上一次listObjects读到的最后一个文件的下一个文件开始继续获取文件列表
            $nextMarker = $listObjectInfo->getNextMarker();
            $listObject = $listObjectInfo->getObjectList();
            $listPrefix = $listObjectInfo->getPrefixList();
//        print_r( ($listObject));
//        print_r( ($listPrefix));
            $dataDir = array_merge($dataDir, $listPrefix);
            $dataFile = array_merge($dataFile, $listObject);
            if ($nextMarker === '') {
                break;
            }
        }
        $data['dir'] = $dataDir;
        $data['file'] = $dataFile;
        //若为空文件夹 $data['dir']没有数据  $data['file']却有。隧数组合并处理
        return array_merge($data['dir'], $data['file']);
    }

    /**
     * 根据对象名判断是否为文件夹
     *
     * @param $name
     * @return bool
     */
    private function isDir($name)
    {
        return strrchr($name, '/') === '/' ? true : false;
    }


    /**
     * 递归删除整个目录
     * 测试时发现此api有问题： 存在的文件夹（里面有个文件）报404。
     * 此方法效率不高，谨慎使用! [删除后最好查看是否操作成功。]
     *
     * @param string $dir
     * @throws Exception
     *
     */
    public function rmrf($dir = 'dd/')
    {
        if ($this->checkAllowIP()) {
            if (strlen($dir) === 0) {
                throw  new Exception('strlen($dir) === 0');
            }

            if ($this->isDir($dir)) {
                $ls = $this->ls($dir);
                foreach ($ls as $item) {
                    try {
                        $name = $item->getPrefix();
                    } catch (Error $e) {
                        $name = $item->getKey();
                    }
                    if ($name != $dir) {//排除自身目录名
                        $this->delDir($name);
                    }
                }
            }
            $this->del($dir);
        }
    }
}
