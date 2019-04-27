<?php
/**
 * Contents.php
 * 
 * 提供内容解析、输出相关的方法
 * 
 * @author      熊猫小A (BigCoke233制作时有修改)
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Contents
{
    /**
     * 文章解析器入口
     * 传入的是经过 Markdown 解析后的文本
     */
    static public function parseContent($text)
    {	
	    //解析灯箱和表格
		$text = preg_replace('/<img(.*?)src="(.*?)"(.*?)alt="(.*?)"(.*?)>/s','<center><a data-fancybox="gallery" href="${2}" class="gallery-link"><img${1}src="${2}"${3}></a></center>',$text);
		$text = preg_replace('/<table>/s','<div class="mdui-table-fluid"><table class="mdui-table">',$text);
		$text = preg_replace('/<\/table>/s','</table></div>',$text);
        
	    //短代码（无参数）
	    $reg = '/\[scode\](.*?)\[\/scode\]/s';
        $rp = '<div class="tip">${1}</div>';
        $text = preg_replace($reg,$rp,$text);
	
        //短代码（有参数）
	    $reg = '/\[scode.*?type="(.*?)"\](.*?)\[\/scode\]/s';
        $rp = '<div class="tip ${1}">${2}</div>';
        $text = preg_replace($reg,$rp,$text);
		
	    //解析嵌入块
	    $reg = '/\[well\](.*?)\[\/well\]/s';
        $rp = '<div class="well">${1}</div>';
        $text = preg_replace($reg,$rp,$text);
		
		//滚动文本区域
	    $reg = '/\[stext\](.*?)\[\/stext\]/s';
        $rp = '<div class="post-stext">${1}</div>';
        $text = preg_replace($reg,$rp,$text);
		
        //解析友链盒子
	    $reg = '/\[links\](.*?)\[\/links\]/s';
        $rp = '<div class="links-box mdui-container-fluid"><div class="mdui-row">${1}</div></div>';
        $text = preg_replace($reg,$rp,$text);
		
		//解析友链项目
	    $reg = '/\[(.*?)\]\{(.*?)\}\((.*?)\)/s';
        $rp = '<a href="${2}" target="_blank" class="links-link">
			<div class="mdui-col-xl-3 mdui-col-lg-3 mdui-col-md-3 mdui-col-sm-4 mdui-col-xs-6">
			  <div class="links-item">
			    <div class="links-img" style="background:url(\'${3}\');width: 100%;padding-top: 100%;background-repeat: no-repeat;background-size: cover;"></div>
				<div class="links-title">
				  <h4>${1}</h4>
				</div>
		      </div>
			</div>
			</a>';
        $text = preg_replace($reg,$rp,$text);
		
        return $text;
    }
	
	/**
     * 评论内容解析器入口
     * 传入的是经过 Markdown 解析后的文本
     */
    static public function parseComment($text)
    {
		
		
		return $text;
	}

    /**
     * 根据 id 返回对应的对象
     * 此方法在 Typecho 1.2 以上可以直接调用 Helper::widgetById();
     * 但是 1.1 版本尚有 bug，因此单独提出放在这里
     * 
     * @param string $table 表名, 支持 contents, comments, metas, users
     * @return Widget_Abstract
     */
    public static function widgetById($table, $pkId)
    {
        $table = ucfirst($table);
        if (!in_array($table, array('Contents', 'Comments', 'Metas', 'Users'))) {
            return NULL;
        }
        $keys = array(
            'Contents'  =>  'cid',
            'Comments'  =>  'coid',
            'Metas'     =>  'mid',
            'Users'     =>  'uid'
        );
        $className = "Widget_Abstract_{$table}";
        $key = $keys[$table];
        $db = Typecho_Db::get();
        $widget = new $className(Typecho_Request::getInstance(), Typecho_Widget_Helper_Empty::getInstance());
        
        $db->fetchRow(
            $widget->select()->where("{$key} = ?", $pkId)->limit(1),
                array($widget, 'push'));
        return $widget;
    }

    /**
     * 输出完备的标题
     */
    public static function title(Widget_Archive $archive)
    {
        $archive->archiveTitle(array(
            'category'  =>  '分类 %s 下的文章',
            'search'    =>  '包含关键字 %s 的文章',
            'tag'       =>  '标签 %s 下的文章',
            'author'    =>  '%s 发布的文章'
        ), '', ' - ');
        Helper::options()->title();
    }

    /**
     * 返回上一篇文章
     */
    public static function getPrev($archive)
    {
        $db = Typecho_Db::get();
        $text = $db->fetchRow($db->select()->from('table.contents')->where('table.contents.created < ?', $archive->created)
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', $archive->type)
            ->where('table.contents.password IS NULL')
            ->order('table.contents.created', Typecho_Db::SORT_DESC)
            ->limit(1));
        
        if($text) {
            return self::widgetById('Contents', $text['cid']);    
        }else{
            return NULL;
        }
    }

    /**
     * 返回下一篇文章
     */
    public static function getNext($archive)
    {
        $db = Typecho_Db::get();
        $text = $db->fetchRow($db->select()->from('table.contents')->where('table.contents.created > ? AND table.contents.created < ?',
            $archive->created, Helper::options()->gmtTime)
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.type = ?', $archive->type)
                ->where('table.contents.password IS NULL')
                ->order('table.contents.created', Typecho_Db::SORT_ASC)
                ->limit(1));

        if($text) {
            return self::widgetById('Contents', $text['cid']);    
        }else{
            return NULL;
        }
    }

    /**
     * 最近评论，过滤引用通告，过滤博主评论
     */
    public static function getRecentComments($num = 10)
    {
        $comments = array();

        $db = Typecho_Db::get();
        $rows = $db->fetchAll($db->select()->from('table.comments')->where('table.comments.status = ?', 'approved')
            ->where('type = ?', 'comment')
            ->where('ownerId <> authorId')
            ->order('table.comments.created', Typecho_Db::SORT_DESC)
            ->limit($num));

        foreach ($rows as $row) {
            $comment =  self::widgetById('Comments', $row['coid']);
            $comments[] = $comment;
        }

        return $comments;
    }
}