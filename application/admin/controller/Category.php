<?php

namespace app\admin\controller;
use app\common\controller\Parser;
use app\common\controller\Backend;
use app\common\model\Category as CategoryModel;
use fast\Tree;

/**
 * 分类管理
 *
 * @icon   fa fa-list
 * @remark 用于管理网站的所有分类,分类可进行无限级分类,分类类型请在常规管理->系统配置->字典配置中添加
 */
class Category extends Backend
{

    /**
     * @var \app\common\model\Category
     */
    protected $model = null;
    protected $categorylist = [];
    protected $noNeedRight = ['selectpage'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\common\model\Category');

        $tree = Tree::instance();
        $tree->init(collection($this->model->order('weigh desc,id desc')->select())->toArray(), 'pid');
        $this->categorylist = $tree->getTreeList($tree->getTreeArray(0), 'name');
        $categorydata = [0 => ['type' => 'all', 'name' => __('None')]];
        foreach ($this->categorylist as $k => $v) {
            $categorydata[$v['id']] = $v;
        }
        $typeList = CategoryModel::getTypeList();
      
        $this->view->assign("typeList", $typeList);
        $this->view->assign("parentList", $categorydata);
        $this->assignconfig('typeList', $typeList);
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            $search = $this->request->request("search");
            $type = $this->request->request("type");

            //构造父类select列表选项数据
            $list = [];

            foreach ($this->categorylist as $k => $v) {
                if ($search) {
                    if ($v['type'] == $type && stripos($v['name'], $search) !== false || stripos($v['nickname'], $search) !== false) {
                        if ($type == "all" || $type == null) {
                            $list = $this->categorylist;
                        } else {
                            $list[] = $v;
                        }
                    }
                } else {
                    if ($type == "all" || $type == null) {
                        $list = $this->categorylist;
                    } elseif ($v['type'] == $type) {
                        $list[] = $v;
                    }
                }
            }

            $total = count($list);
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        return parent::add();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($params['pid'] != $row['pid']) {
                    $childrenIds = Tree::instance()->init(collection(\app\common\model\Category::select())->toArray())->getChildrenIds($row['id'], true);
                    if (in_array($params['pid'], $childrenIds)) {
                        $this->error(__('Can not change the parent to child or itself'));
                    }
                }

                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    if ($result !== false) {
                        $this->success();
                    } else {
                        $this->error($row->getError());
                    }
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                } catch (\think\Exception $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    public function ipaInfo($filePath) {
        //$filePath='/www/wwwroot/sign.52tzs.com/public/uploads/20220420/7a2e2043dbe8641e7d187850575335fe.ipa';
        $locale = 'zh_CN.UTF-8';
        setlocale(LC_ALL, $locale);
        putenv('LC_ALL='.$locale);
		//var_dump($ipa_file);
		// 获取文件地址
	    $md5=substr(substr($filePath,-36),0,32);
		$filesPath = $_SERVER['DOCUMENT_ROOT']."/cos/".$md5;
		// 解压文件
		$_cmd = 'unzip -u '.$filePath.' -d '.$filesPath;
		exec($_cmd,$output,$return_var);

		//查询文件.app并去掉后缀
		$dir = iconv ( "gbk", "utf-8", $filesPath.'/Payload' );
		$fileName = scandir($dir);
		foreach($fileName as $f) {
            if(strpos($f,'.app')){
               	$newFileName =$f;
            }}
	//	$file_name = explode('.app',$newFileName);
		$newFile_name = $newFileName;//$file_name[0];
	
    
		// 加载插件
		$path = str_replace("application/admin/controller","application/common/", dirname(__FILE__));
		require_once($path.'CFPropertyList/CFType.php');
		require_once($path.'CFPropertyList/CFArray.php');
		require_once($path.'CFPropertyList/CFBoolean.php');
		require_once($path.'CFPropertyList/CFData.php');
		require_once($path.'CFPropertyList/CFDate.php');
		require_once($path.'CFPropertyList/CFDictionary.php');
		require_once($path.'CFPropertyList/CFNumber.php');
		require_once($path.'CFPropertyList/CFString.php');
		require_once($path.'CFPropertyList/CFTypeDetector.php');
		require_once($path.'CFPropertyList/CFUid.php');
		require_once($path.'CFPropertyList/IOException.php');
		require_once($path.'CFPropertyList/PListException.php');
		require_once($path.'CFPropertyList/CFBinaryPropertyList.php');
		require_once($path.'CFPropertyList/CFPropertyList.php');

		
			$content =file_get_contents($filesPath.'/Payload/'.$newFile_name.'/Info.plist');
			$plist = new \CFPropertyList\CFPropertyList();
			$plist->parse($content);
			$data = $plist->toArray();

			$info['icon'] = '';
			
            $info['name'] = isset($data['CFBundleDisplayName']) ?$data['CFBundleDisplayName'] : $data['CFBundleName'];
              
			$info['sys_name'] = $data['CFBundleIdentifier'];
			$info['version'] = $data['CFBundleShortVersionString'];
			//$info['version_code'] = $data['CFBundleVersion'];
			// 获取图标

			//$CFBundleIcons = $data['CFBundleIcons']['CFBundlePrimaryIcon']['CFBundleIconFiles'];
			$iconName = isset($data['CFBundleIcons'])?$data['CFBundleIcons']['CFBundlePrimaryIcon']['CFBundleIconFiles']:$data['CFBundleIconFiles'];
         
			//['CFBundleIcons']

			$iconName =  end($iconName);
		
            if(strstr($iconName,'png')){
              $icon_name1 = $filesPath.'/Payload/'.$newFile_name.'/'.$iconName;   
            }else{
            $icon_name1 = $filesPath.'/Payload/'.$newFile_name.'/'.$iconName.'.png';
			$icon_name2 = $filesPath.'/Payload/'.$newFile_name.'/'.$iconName.'@2x.png';
			$icon_name3 = $filesPath.'/Payload/'.$newFile_name.'/'.$iconName.'@3x.png';
            }
		
			
			$temp_path = $filesPath.'/';
			if (!file_exists($temp_path)) {
				mkdir($temp_path,0777,true);
			}

			//require_once($path . '/helpers/parsers_helper.php');
			$filename = null;
			if (file_exists($icon_name1)) {
				$filename = $icon_name1; //需要解密的文件路径
			}elseif (file_exists($icon_name2)){
				$filename = $icon_name2; //需要解密的文件路径
			}elseif (file_exists($icon_name3)){
				$filename = $icon_name3; //需要解密的文件路径
			}
            $parser = new Parser();
			if ($filename !== null) {
			    $path = 'cos/'.str_replace(' ','',$info['name']);
                if (!file_exists($path)){
                    mkdir($path); 
                }
			    $_rename = 'mv '.$filePath.'  '.$_SERVER['DOCUMENT_ROOT']."/cos/".str_replace(' ','',$info['name']).'/'.$info['version'].'.ipa';
		        exec($_rename,$out,$return);
				$newFilename = 'cos/'.str_replace(' ','',$info['name']).'/'.$info['version'].'_'.$iconName.'.png'; //解密后的文件路径
				Parser::fix($filename,$newFilename);
				$info['icon'] ='https://'.$_SERVER['HTTP_HOST'].'/cos/'.str_replace(' ','',$info['name']).'/'.$info['version'].'_'.$iconName.'.png';
				$info['url'] =$_SERVER['DOCUMENT_ROOT']."/cos/".str_replace(' ','',$info['name']).'/'.$info['version'].'.ipa';
			}
		    exec('rm -rf '.$filePath);
		    exec('rm -rf '.$_SERVER['DOCUMENT_ROOT']."/cos/".$md5);
			return json($info);
	
	}


    /**
     * Selectpage搜索
     *
     * @internal
     */
    public function selectpage()
    {
        return parent::selectpage();
    }
}
