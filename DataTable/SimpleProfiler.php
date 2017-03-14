<?php

/*
 * The MIT License
 *
 * Copyright 2017 Rafael Nájera <rafael@najera.ca>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
namespace DataTable;

/**
 * SimpleProfiler class
 * 
 * Adapted from http://stackoverflow.com/questions/21133/simplest-way-to-profile-a-php-script
 *
 * @author Rafael Nájera <rafael@najera.ca>
 */
class SimpleProfiler {
    
    private $timingPoints = [];
        
    /**
     * 
     * @param string $description  a descriptive string
     * @param int $iterations number of iterations or actions done
     */
    public function timingPoint($description, $iterations = 1)
    {
        array_push($this->timingPoints, ['name' => $description, 'time'=> microtime(true), 'n'=> $iterations]);

    }

    public function start(){
        $this->timingPoint('Start');
    }
    
    public function getReport()
    {
        
        $size = count($this->timingPoints);
        $s = "\n";

        for( $i=1; $i < $size; $i++)
        {
            $lapTime = $this->timingPoints[$i]['time'] - $this->timingPoints[$i-1]['time'];
            $iterationTime = $lapTime / $this->timingPoints[$i]['n'];
            $s .= "[" . $this->timingPoints[$i]['name'] . "]\n";
            $s .= sprintf("   %f secs", $lapTime);
            if ($this->timingPoints[$i]['n'] > 1){
                $s .= sprintf(" (%d x %f secs)", 
                        $this->timingPoints[$i]['n'], 
                        $iterationTime);
            }
            $s .= "\n";
        }
       return $s;
    }
    
}
