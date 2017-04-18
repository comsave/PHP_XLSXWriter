<?php

class XLSWriterPlus extends XLSXWriter
{
    /**
     * @var array
     */
    private $images = [];

    /**
     * @var array
     */
    private $imageOptions = [];

    /**
     * @var array
     */
    private $ignoredErrorsCells = [];

    /**
     * @return array
     */
    public function getIgnoredErrorsCells()
    {
        return $this->ignoredErrorsCells;
    }

    /**
     * @param array $ignoredErrorsCells
     */
    public function setIgnoredErrorsCells($ignoredErrorsCells)
    {
        $this->ignoredErrorsCells = $ignoredErrorsCells;
    }

    /**
     * @return array
     */
    public function getSheets()
    {
        return $this->sheets;
    }

    /**
     * @param string $imagePath
     * @param array $imageOptions
     * @param int $imageId
     * @throws Exception
     */
    public function addImage($imagePath, $imageId, $imageOptions = [])
    {
        if(!file_exists($imagePath)){
            throw new Exception(sprintf('File %s not found.', $imagePath));
        }

        $this->images[$imageId] = $imagePath;

        if (empty($imageOptions)) {
            $imageOptions = [
                'startColNum' => 0,
                'endColNum' => 0,
                'startRowNum' => 0,
                'endRowNum' => 0
            ];
        }
        $this->imageOptions[$imageId] = $imageOptions;
    }

    public function writeToString()
    {
        $temp_file = $this->tempFilename();
        $this->writeToFile($temp_file);
        $string = file_get_contents($temp_file);

        return $string;
    }

    /**
     * @param $filename
     * @throws Exception
     */
    public function writeToFile($filename)
    {
        foreach ($this->sheets as $sheet_name => $sheet) {
            $this->finalizeSheet($sheet_name);
        }

        if (file_exists($filename)) {
            if (is_writable($filename)) {
                @unlink($filename);
            } else {
                throw new \Exception("Error in " . __CLASS__ . "::" . __FUNCTION__ . ", file is not writeable.");
            }
        }

        $zip = new \ZipArchive();
        if (empty($this->sheets)) {
            throw new \Exception("Error in " . __CLASS__ . "::" . __FUNCTION__ . ", no worksheets defined.");
        }
        if (!$zip->open($filename, \ZipArchive::CREATE)) {
            throw new \Exception("Error in " . __CLASS__ . "::" . __FUNCTION__ . ", unable to create zip.");
        }

        $zip->addEmptyDir("_rels/");
        $zip->addFromString("_rels/.rels", $this->buildRelationshipsXML());

        if (count($this->images) > 0) {
            $zip->addEmptyDir("xl/media/");

            $zip->addEmptyDir("xl/drawings");
            $zip->addEmptyDir("xl/drawings/_rels");

            foreach ($this->images as $imageId => $imagePath) {
                $imageName = explode('/', $imagePath);
                $imageName = end($imageName);

                $zip->addFile($imagePath, 'xl/media/' . $imageName);

                $i = 1;
                foreach ($this->sheets as $sheet) {
                    $zip->addFromString("xl/drawings/drawing" . $i . ".xml", $this->buildDrawingXML($imagePath, $imageId));
                    $zip->addFromString("xl/drawings/_rels/drawing" . $i . ".xml.rels", $this->buildDrawingRelationshipXML());
                    $i++;
                }
            }
        }

        $zip->addEmptyDir("xl/worksheets/");
        $zip->addEmptyDir("xl/worksheets/_rels/");

        $i = 1;
        foreach ($this->sheets as $sheet) {
            $zip->addFile($sheet->filename, "xl/worksheets/" . $sheet->xmlname);
            $zip->addFromString("xl/worksheets/_rels/" . $sheet->xmlname . '.rels', $this->buildSheetRelationshipXML($i++));
        }

        $zip->addFromString("xl/workbook.xml", $this->buildWorkbookXML());
        $zip->addFile($this->writeStylesXML(), "xl/styles.xml");
        $zip->addFromString("[Content_Types].xml", $this->buildContentTypesXML());

        $zip->addEmptyDir("xl/_rels/");
        $zip->addFromString("xl/_rels/workbook.xml.rels", $this->buildWorkbookRelsXML());
        $zip->close();
    }

    /**
     * @param string $imagePath
     * @param int $imageId
     * @return string
     */
    public function buildDrawingXML($imagePath, $imageId)
    {
        $cellWidth = 875000;
        $cellHeight = 170000;

        $imageOptions = $this->imageOptions[$imageId];
        list($width, $height) = getimagesize($imagePath);
        $ratio = $width / $height;

        $offsetX = round($cellWidth * ($ratio - floor($ratio))) + 115000;
        $offsetY = 25000;

        $cX = 2419350;
        $cY = 790575;

        $imageRelationshipXML = '<?xml version="1.0" encoding="UTF-8"?>
            <xdr:wsDr xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing">
                <xdr:twoCellAnchor editAs="oneCell">
                    <xdr:from>
                        <xdr:col>' . $imageOptions['startColNum'] . '</xdr:col>
                        <xdr:colOff>0</xdr:colOff>
                        <xdr:row>' . $imageOptions['startRowNum'] . '</xdr:row>
                        <xdr:rowOff>0</xdr:rowOff>
                    </xdr:from>
                    <xdr:to>' . $imageOptions['endColNum'] . '
                        <xdr:col>' . $imageOptions['endColNum'] . '</xdr:col>
                        <xdr:colOff>' . $offsetX . '</xdr:colOff>
                        <xdr:row>' . $imageOptions['endRowNum'] . '</xdr:row>
                        <xdr:rowOff>' . $offsetY . '</xdr:rowOff>
                    </xdr:to>
                    <xdr:pic>
                        <xdr:nvPicPr>
                            <xdr:cNvPr id="2" name="Picture ' . $imageId . '" />
                            <xdr:cNvPicPr>
                              <a:picLocks noChangeAspect="0"/>
                            </xdr:cNvPicPr>
                        </xdr:nvPicPr>
                        <xdr:blipFill>
                        <a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rId' . $imageId . '">
                          <a:extLst>
                            <a:ext uri="{28A0092B-C50C-407E-A947-70E740481C1C}">
                              <a14:useLocalDpi xmlns:a14="http://schemas.microsoft.com/office/drawing/2010/main" val="0"/>
                            </a:ext>
                          </a:extLst>
                        </a:blip>
                        <a:stretch>
                          <a:fillRect/>
                        </a:stretch>
                      </xdr:blipFill>
                      <xdr:spPr>
                        <a:xfrm>
                          <a:off x="0" y="0"/>
                          <a:ext cx="' . $cX . '" cy="' . $cY . '"/>
                        </a:xfrm>
                        <a:prstGeom prst="rect">
                          <a:avLst/>
                        </a:prstGeom>
                      </xdr:spPr>
                    </xdr:pic>
                    <xdr:clientData/>
                </xdr:twoCellAnchor>
            </xdr:wsDr>
        ';

        return $imageRelationshipXML;
    }

    /**
     * @return string
     */
    protected function buildContentTypesXML()
    {
        $content_types_xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $content_types_xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $content_types_xml .= '<Default ContentType="application/xml" Extension="xml"/>';
        $content_types_xml .= '<Default ContentType="image/png" Extension="png"/>';
        $content_types_xml .= '<Default ContentType="application/vnd.openxmlformats-package.relationships+xml" Extension="rels"/>';
        foreach ($this->sheets as $sheet_name => $sheet) {
            $content_types_xml .= '<Override PartName="/xl/worksheets/' . ($sheet->xmlname) . '" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $content_types_xml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $content_types_xml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        if (count($this->images) > 0) {
            $i = 1;
            foreach ($this->sheets as $sheet_name => $sheet) {
                $content_types_xml .= '<Override PartName="/xl/drawings/drawing' . ($i++) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml" />';
            }
        }
        $content_types_xml .= "\n";
        $content_types_xml .= '</Types>';

        return $content_types_xml;
    }

    /**
     * @param $sheet_name
     */
    protected function finalizeSheet($sheet_name)
    {
        if (empty($sheet_name) || $this->sheets[$sheet_name]->finalized)
            return;

        $sheet = &$this->sheets[$sheet_name];

        $sheet->file_writer->write('</sheetData>');

        if (!empty($sheet->merge_cells)) {
            $sheet->file_writer->write('<mergeCells>');
            foreach ($sheet->merge_cells as $range) {
                $sheet->file_writer->write('<mergeCell ref="' . $range . '"/>');
            }
            $sheet->file_writer->write('</mergeCells>');
        }

        $sheet->file_writer->write('<printOptions headings="false" gridLines="false" gridLinesSet="true" horizontalCentered="false" verticalCentered="false"/>');
        $sheet->file_writer->write('<pageMargins left="0.5" right="0.5" top="1.0" bottom="1.0" header="0.5" footer="0.5"/>');
        $sheet->file_writer->write('<pageSetup blackAndWhite="false" cellComments="none" copies="1" draft="false" firstPageNumber="1" fitToHeight="1" fitToWidth="1" horizontalDpi="300" orientation="portrait" pageOrder="downThenOver" paperSize="1" scale="100" useFirstPageNumber="true" usePrinterDefaults="false" verticalDpi="300"/>');
        $sheet->file_writer->write('<headerFooter differentFirst="false" differentOddEven="false">');
        $sheet->file_writer->write('<oddHeader>&amp;C&amp;&quot;Times New Roman,Regular&quot;&amp;12&amp;A</oddHeader>');
        $sheet->file_writer->write('<oddFooter>&amp;C&amp;&quot;Times New Roman,Regular&quot;&amp;12Page &amp;P</oddFooter>');
        $sheet->file_writer->write('</headerFooter>');
        if(count($this->getIgnoredErrorsCells()) > 0){
            $sheet->file_writer->write('<ignoredErrors>');
            foreach($this->getIgnoredErrorsCells() as $ignoredErrorsCell) {
                $sheet->file_writer->write('<ignoredError sqref="' . $ignoredErrorsCell . '" numberStoredAsText="1"/>');
            }
            $sheet->file_writer->write('</ignoredErrors>');
        }
        if (count($this->images) > 0) {
            foreach ($this->images as $imageId => $imagePath) {
                $sheet->file_writer->write('<drawing r:id="rId' . $imageId . '" />');
            }
        }
        $sheet->file_writer->write('</worksheet>');

        $max_cell = $this->xlsCell($sheet->row_count - 1, count($sheet->columns) - 1);
        $max_cell_tag = '<dimension ref="A1:' . $max_cell . '"/>';
        $padding_length = $sheet->max_cell_tag_end - $sheet->max_cell_tag_start - strlen($max_cell_tag);
        $sheet->file_writer->fseek($sheet->max_cell_tag_start);
        $sheet->file_writer->write($max_cell_tag . str_repeat(" ", $padding_length));
        $sheet->file_writer->close();
        $sheet->finalized = true;
    }

    /**
     * @return string
     */
    public function buildDrawingRelationshipXML()
    {
        $drawingXML = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $drawingXML .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        foreach ($this->images as $imageId => $imagePath) {
            $imageName = explode('/', $imagePath);
            $imageName = end($imageName);

            $drawingXML .= '<Relationship Id="rId' . $imageId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/' . $imageName . '"/>';
        }

        $drawingXML .= "\n" . '</Relationships>';

        return $drawingXML;
    }

    /**
     * @param int $sheetId
     * @return string
     */
    public function buildSheetRelationshipXML($sheetId)
    {
        $lastRelationshipId = 0;

        $rels_xml = "";
        $rels_xml .= '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $rels_xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        if (count($this->images) > 0) {
            foreach ($this->images as $imageId => $imagePath) {
                $rels_xml .= '<Relationship Id="rId' . (++$lastRelationshipId) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing' . $sheetId . '.xml"/>';
            }
        }

        $rels_xml .= "\n";
        $rels_xml .= '</Relationships>';

        return $rels_xml;
    }
}