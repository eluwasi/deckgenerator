<?php
class SimplePPTX {
    private $slides = array();
    private $tempDir;
    
    public function __construct() {
        $this->tempDir = WP_CONTENT_DIR . '/uploads/temp-' . uniqid();
        wp_mkdir_p($this->tempDir);
        
        // Create basic structure
        wp_mkdir_p($this->tempDir . '/ppt/slides');
        wp_mkdir_p($this->tempDir . '/ppt/media');
        wp_mkdir_p($this->tempDir . '/_rels');
    }
    
    public function addSlide($title, $content) {
        $slideNum = count($this->slides) + 1;
        $this->slides[] = array(
            'num' => $slideNum,
            'title' => $title,
            'content' => $content
        );
    }
    
    public function save($filename) {
        // Create presentation.xml
        $presentationXml = $this->createPresentationXml();
        file_put_contents($this->tempDir . '/ppt/presentation.xml', $presentationXml);
        
        // Create slides
        foreach ($this->slides as $slide) {
            $slideXml = $this->createSlideXml($slide);
            file_put_contents(
                $this->tempDir . '/ppt/slides/slide' . $slide['num'] . '.xml',
                $slideXml
            );
        }
        
        // Create [Content_Types].xml
        $contentTypesXml = $this->createContentTypesXml();
        file_put_contents($this->tempDir . '/[Content_Types].xml', $contentTypesXml);
        
        // Create _rels/.rels
        $relsXml = $this->createRelsXml();
        file_put_contents($this->tempDir . '/_rels/.rels', $relsXml);
        
        // Create ZIP archive
        $zip = new ZipArchive();
        $zipFile = WP_CONTENT_DIR . '/uploads/' . $filename;
        
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $this->addDirToZip($this->tempDir, $zip);
            $zip->close();
        }
        
        // Cleanup
        $this->rrmdir($this->tempDir);
        
        return content_url() . '/uploads/' . $filename;
    }
    
    private function createPresentationXml() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
            <p:sldIdLst>';
            
        foreach ($this->slides as $slide) {
            $xml .= '<p:sldId id="' . (256 + $slide['num']) . '" r:id="rId' . $slide['num'] . '"/>';
        }
        
        $xml .= '</p:sldIdLst>
            <p:sldSz cx="9144000" cy="6858000"/>
            <p:notesSz cx="6858000" cy="9144000"/>
        </p:presentation>';
        
        return $xml;
    }
    
    private function createSlideXml($slide) {
        return '<?xml version="1.0" encoding="UTF-8"?>
        <p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
            <p:cSld>
                <p:spTree>
                    <p:sp>
                        <p:txBody>
                            <a:p>
                                <a:r>
                                    <a:t>' . htmlspecialchars($slide['title']) . '</a:t>
                                </a:r>
                            </a:p>
                            <a:p>
                                <a:r>
                                    <a:t>' . htmlspecialchars($slide['content']) . '</a:t>
                                </a:r>
                            </a:p>
                        </p:txBody>
                    </p:sp>
                </p:spTree>
            </p:cSld>
        </p:sld>';
    }
    
    private function createContentTypesXml() {
        return '<?xml version="1.0" encoding="UTF-8"?>
        <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
            <Default Extension="xml" ContentType="application/xml"/>
            <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
            <Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>
            <Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>
        </Types>';
    }
    
    private function createRelsXml() {
        return '<?xml version="1.0" encoding="UTF-8"?>
        <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
            <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="/ppt/presentation.xml"/>
        </Relationships>';
    }
    
    private function addDirToZip($dir, $zip, $relative = '') {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            
            $filePath = $dir . '/' . $file;
            $zipPath = $relative . ($relative ? '/' : '') . $file;
            
            if (is_dir($filePath)) {
                $zip->addEmptyDir($zipPath);
                $this->addDirToZip($filePath, $zip, $zipPath);
            } else {
                $zip->addFile($filePath, $zipPath);
            }
        }
    }
    
    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object))
                        $this->rrmdir($dir . "/" . $object);
                    else
                        unlink($dir . "/" . $object);
                }
            }
            rmdir($dir);
        }
    }
}