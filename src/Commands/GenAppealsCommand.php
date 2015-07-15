<?php
/*
 * This file is part of the PHPConsole package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*/
namespace Longman\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package     PHPConsole
 * @author       Avtandil Kikabidze <akalongman@gmail.com>
 * @copyright   2015 Avtandil Kikabidze <akalongman@gmail.com>
 * @license       http://opensource.org/licenses/mit-license.php  The MIT License (MIT)
 * @link            http://www.github.com/akalongman/php-console
*/
class GenAppealsCommand extends Command
{
    protected $path;

    protected function configure()
    {

        $this->setName("longman:gen_appeals")
             ->setDescription("Generate appeals from images")
             ->setDefinition(array(
                 new InputOption('path', 'p', InputOption::VALUE_OPTIONAL, 'Path of images', getcwd()),
             ))
            ->setHelp(<<<EOT
Generates coordinates

Usage:

<info>longman:gen_appeals <env></info>

EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //$header_style = new OutputFormatterStyle('yellow', 'black', array('bold'));
        //$output->getFormatter()->setStyle('header', $header_style);


        $path = $input->getOption('path');
        if (!is_dir($path)) {
            throw new \InvalidArgumentException('Path ' . $path . ' not found!');
        }
        $this->path = $path;


        $iterator = new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);
        $files = array();
        foreach ($iterator as $fileinfo) {
            $file = $fileinfo->getPathname();
            $ext  = $fileinfo->getExtension();
            if (strtolower($ext) != 'jpg') {
                continue;
            }
            $name = $fileinfo->getBasename('.jpg');
            $files[$name] = $file;
        }

        if (empty($files)) {
            throw new \InvalidArgumentException('No images found in path ' . $path . '');
        }


        foreach ($files as $name => $file) {
            list($lat, $lng) = $this->getLatLng($file, true);

            // generate appeal report
            $output->write('<comment>Generating appeal for "'.$name.'": </comment>', false);

            $status = $this->generate($name, array($lat, $lng));

            if ($status) {
                 $output->write('<info>Success</info>', true);
            } else {
                 $output->write('<error>Error</error>', true);
            }


        }



    }

    protected function getLatLng($path, $decimal = false)
    {

        $im = new \Imagick($path);

        $exifArray = $im->getImageProperties("exif:*");

        $GPSLatitude     = $exifArray['exif:GPSLatitude'];
        $GPSLatitudeRef  = $exifArray['exif:GPSLatitudeRef'];
        $GPSLongitude    = $exifArray['exif:GPSLongitude'];
        $GPSLongitudeRef = $exifArray['exif:GPSLongitudeRef'];

        $ex1 = explode(',', $GPSLatitude);
        if (empty($ex1)) {
            return array(false, false);
        }
        $ex1_1 = explode('/', $ex1[0]);
        $ex1_2 = explode('/', $ex1[1]);
        $ex1_3 = explode('/', $ex1[2]);

        $D = $ex1_1[0] / $ex1_1[1];
        $M = $ex1_2[0] / $ex1_2[1];
        $S = $ex1_3[0] / $ex1_3[1];

        if ($decimal) {
            $latitude = $this->toDecimal($D, $M, $S, $GPSLatitudeRef);
        } else {
            $latitude = $D . '° ' . $M . '\' ' . $S . '" ' . $GPSLatitudeRef;
        }

        $ex2 = explode(',', $GPSLongitude);
        if (empty($ex2)) {
            return array(false, false);
        }
        $ex2_1 = explode('/', $ex2[0]);
        $ex2_2 = explode('/', $ex2[1]);
        $ex2_3 = explode('/', $ex2[2]);

        $D = $ex2_1[0] / $ex2_1[1];
        $M = $ex2_2[0] / $ex2_2[1];
        $S = $ex2_3[0] / $ex2_3[1];

        if ($decimal) {
            $longitude = $this->toDecimal($D, $M, $S, $GPSLongitudeRef);
        } else {
            $longitude = $D . '° ' . $M . '\' ' . $S . '" ' . $GPSLongitudeRef;
        }

        return array($latitude, $longitude);
    }

    protected function toDecimal($degrees, $minutes, $seconds, $direction)
    {
        $dd = $degrees + $minutes / 60 + $seconds / (60 * 60);

        if ("S" == $direction || "W" == $direction) {
            $dd = $dd * -1;
        }
        return $dd;
    }

    protected function generate($name, array $lat_lng)
    {
        $template = <<<TMP
- Portal Title: {{TITLE}}
- City, Country: Tbilisi, Georgia
- Lat/long(s): @{{LAT}},{{LONG}} (https://www.google.com/maps/place/{{LAT}},{{LONG}})
- Appeal category: New Portal Submission
- Reason/comments: Approve please, this is good candidate for portal

Dear +NIA Ops

TEXT

*This place is public, open 24/24 and accessible for everyone. Nice portal candidate I think.*

#PortalAppeals﻿
#PortalWorthy﻿
#Georgia
#Tbilisi﻿
TMP;

        $template = str_replace('{{TITLE}}', $name, $template);
        $template = str_replace('{{LAT}}', $lat_lng[0], $template);
        $template = str_replace('{{LONG}}', $lat_lng[1], $template);

        $file = $this->path.'/'.$name.'.txt';
        $status = file_put_contents($file, $template);
        chmod($file, 0777);
        return $status;
    }
}
