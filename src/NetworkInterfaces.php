<?php
/*
 * This file is part of NetworkInterfaces.
 *
 * (c) Pedram Azimaie <carp3co@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NetworkInterfaces;


use Exception;

/**
 * Class NetworkInterfaces
 * @package NetworkInterfaces
 */
class NetworkInterfaces
{
    /**
     * @var Adaptor[]
     */
    public $Adaptors = [];
    /**
     * @var bool|string
     */
    private $_interfaceFile = false;
    /**
     * @var bool|string
     */
    private $_interfaceContent = '';
    /**
     * @var bool
     */
    private $_interfaceLoaded = false;
    /**
     * @var bool
     */
    private $_interfaceParsed = false;

    /**
     * NetworkInterfaces constructor.
     * @param string $InterfacePath Path to interface file, usually /etc/network/interfaces
     * @param bool $new skip reading interface file, useful for creating new file
     * @throws Exception
     */
    public function __construct(string $InterfacePath = '/etc/network/interfaces', $new = False)
    {
        $this->_interfaceFile = $InterfacePath;
        if ($new) {
            $this->_interfaceParsed = true;
            return;
        }
        if (!@file_exists($this->_interfaceFile))
            throw new Exception("Interface file does not exist");
        if (!@is_readable($this->_interfaceFile))
            throw new Exception("Interface file is not readable");
        $this->_interfaceContent = file_get_contents($this->_interfaceFile);
        $this->_interfaceLoaded = true;
    }

    /**
     * read interface file and fill Adaptor property
     * @return array
     * @throws Exception
     */
    public function parse()
    {
        if (!$this->_interfaceLoaded)
            throw new Exception("Interface file is not loaded");
        $interfaceContent = explode("\n", $this->_interfaceContent);
        $lastAdaptor = '';
        foreach ($interfaceContent as $item) {
            $item = trim($item);

            if (strpos(ltrim($item), '#') === 0) continue;
            if (trim($item) == '') continue;
            if (strpos($item, 'iface') === 0)
                $lastAdaptor = $this->_parseIface($item);
            elseif (strpos($item, 'auto') === 0)
                $this->_parseAuto($item);
            elseif (strpos($item, 'allow-') === 0)
                $this->_parseAllow($item);
            elseif ($lastAdaptor != '')
                $this->_parseDetail($item, $lastAdaptor);
        }
        $this->_interfaceParsed = true;
        return $this->Adaptors;
    }

    /**
     * @param $item
     * @return mixed
     */
    private function _parseIface($item)
    {
        $chunks = $this->_split($item);
        list($null, $this->Adaptors[$chunks[1]]->name, $this->Adaptors[$chunks[1]]->family, $this->Adaptors[$chunks[1]]->method) = $chunks;
        unset($null);
        return $chunks[1];
    }

    /**
     * @param $item
     * @return array
     */
    private function _split($item, $adaptor = False, $returnAdaptor = false)
    {
        $chunks = preg_split('/\s+/', $item, -1, PREG_SPLIT_NO_EMPTY);
        if (!$adaptor) $this->_addAdaptor($chunks[1]);
        return $returnAdaptor ? $chunks[1] : $chunks;
    }


    /**
     * @param $adaptor
     */
    private function _addAdaptor($adaptor)
    {
        if (!array_key_exists($adaptor, $this->Adaptors)) $this->Adaptors[$adaptor] = new Adaptor();
        $this->Adaptors[$adaptor]->auto = false;
    }

    /**
     * @param $item
     */
    private function _parseAuto($item)
    {
        $chunks = $this->_split($item);
        foreach (array_slice($chunks, 1) as $chunk) {
            $this->_addAdaptor($chunk);
            $this->Adaptors[$chunk]->auto = True;
        }
    }

    /**
     * @param $item
     */
    private function _parseAllow($item)
    {
        $chunks = $this->_split($item);
        $allow = str_replace('allow-', '', $chunks[0]);
        $allow = trim($allow);
        if (!in_array($allow, $this->Adaptors[$chunks[1]]->allows)) $this->Adaptors[$chunks[1]]->allows[] = $allow;
    }

    /**
     * @param $item
     * @param $lastAdaptor
     */
    private function _parseDetail($item, $lastAdaptor)
    {
        $chunks = $this->_split($item, $lastAdaptor);
        $adaptor = &$this->Adaptors[$lastAdaptor];
        switch ($chunks[0]) {
            case 'address':
                if(strpos($chunks[1], '/') == false)
                    $adaptor->address = $chunks[1];
                else
                {
                    $chunks[1] =  $this->_parseCidr($chunks[1]);
                    $adaptor->address = $chunks[1]["address"];
                    $adaptor->netmask = $chunks[1]["netmask"];
                    $adaptor->broadcast = $chunks[1]["broadcast"];
                    $adaptor->network = $chunks[1]["network"];
                }
                break;
            case 'netmask':
                $adaptor->netmask = $chunks[1];
                break;
            case 'gateway':
                $adaptor->gateway = $chunks[1];
                break;
            case 'broadcast':
                $adaptor->broadcast = $chunks[1];
                break;
            case 'network':
                $adaptor->network = $chunks[1];
                break;
            default:
                $adaptor->Unknown[] = trim($item);
                break;
        }
    }

    function _parseCidr($cidr) {
        $range = array();
        $cidr = explode('/', $cidr);
        $range["address"] = $cidr[0];
        $range["network"] = long2ip((ip2long($cidr[0])) & ((-1 << (32 - (int)$cidr[1]))));
        $range["broadcast"] = long2ip((ip2long($range["network"])) + pow(2, (32 - (int)$cidr[1])) - 1);
        $range["netmask"] = long2ip(-1 << (32 - (int)$cidr[1]));
        return $range;
    }

    /**
     * brings up an interface
     * @param string $name Interface name
     * @param bool $sudo use sudo command before ifup
     * @throws Exception
     */
    public function up($name, $sudo = false)
    {
        if (!$this->_interfaceParsed)
            throw new Exception("Interface file is not parsed");
        if (!array_key_exists($name, $this->Adaptors))
            throw new Exception("$name does not exist is adaptor list");
        $cmd = ($sudo ? 'sudo ' : '') . "ip link set $name up";
        shell_exec($cmd);
    }

    /**
     * brings down an interface
     * @param string $name Interface name
     * @param bool $sudo use sudo command before ifdown
     * @throws Exception
     */
    public function down($name, $sudo = false)
    {
        if (!$this->_interfaceParsed)
            throw new Exception("Interface file is not parsed");
        if (!array_key_exists($name, $this->Adaptors))
            throw new Exception("$name does not exist is adaptor list");
        $cmd = ($sudo ? 'sudo ' : '') . "ip link set $name down";
        shell_exec($cmd);
    }

    /**
     * get mac adrress of interface
     * @param string $name Interface name
     * @param bool $sudo use sudo command before ip
     * @throws Exception
     */
    public function mac($name, $sudo = false)
    {
        $cmd = ($sudo ? 'sudo ' : '') . "ip link show $name | grep 'link/ether' | awk '{print $2}'";
        return trim(shell_exec($cmd));
    }

    /**
     * get address of interface
     * @param string $name Interface name
     * @param bool $sudo use sudo command before ip
     * @throws Exception
     */
    public function address($name, $sudo = false)
    {
        $cmd = ($sudo ? 'sudo ' : '') . "ip addr show $name | grep 'inet ' | awk '{print $2}' | cut -d'/' -f1";
        return trim(shell_exec($cmd));
    }

    /**
     * get netmask of interface
     * @param string $name Interface name
     * @param bool $sudo use sudo command before ip
     * @throws Exception
     */
    public function netmask($name, $sudo = false)
    {
        $cmd = ($sudo ? 'sudo ' : '') . "ip addr show $name | grep 'inet ' | awk '{print $2}' | cut -d'/' -f2";
        return trim(shell_exec($cmd));
    }

    /**
     * Get the status of an interface (up or down)
     * @param string $name Interface name
     * @param bool $sudo use sudo command before checking the status
     * @return string "up" or "down"
     * @throws Exception
     */
    public function status($name, $sudo = false)
    {
        if (!array_key_exists($name, $this->Adaptors)) {
            throw new Exception("$name does not exist in the adaptor list");
        }
        
        $cmd = ($sudo ? 'sudo ' : '') . "ip link show $name | grep 'state' | awk '{print $9}'";
        $status = trim(shell_exec($cmd));
    
        if ($status === "UP") {
            return "up";
        } elseif ($status === "DOWN") {
            return "down";
        } else {
            throw new Exception("Unable to determine the status of the interface $name");
        }
    }
    
    /**
     * get mtu of interface
     * @param string $name Interface name
     * @param bool $sudo use sudo command before ip
     * @throws Exception
     */
    public function mtu($name, $sudo = false)
    {
        $cmd = ($sudo ? 'sudo ' : '') . "ip link show $name | grep 'mtu' | awk '{print $5}'";
        return trim(shell_exec($cmd));
    }

    /**
     * get speed of interface
     * @param string $name Interface name
     * @param bool $sudo use sudo command before ethtool
     * @throws Exception
     */
    public function speed($name, $sudo = false)
    {
        $cmd = ($sudo ? 'sudo ' : '') . "ethtool $name | grep 'Speed:' | awk '{print $2}'";
        return shell_exec($cmd);
    }

    /**
     * get duplex of interface
     * @param string $name Interface name
     * @param bool $sudo use sudo command before ethtool
     * @throws Exception
     */
    public function duplex($name, $sudo = false)
    {
        $cmd = ($sudo ? 'sudo ' : '') . "ethtool $name | grep 'Duplex:' | awk '{print $2}'";
        return shell_exec($cmd);
    }

    /**
     * get gateway of interface
     * @param string $name Interface name
     * @param bool $sudo use sudo command before ip route
     * @throws Exception
     */
    public function gateway($name, $sudo = false)
    {
        $cmd = ($sudo ? 'sudo ' : '') . "ip route show default | grep $name | awk '{print $3}'";
        return trim(shell_exec($cmd));
    }

    /**
     * restart an interface
     * @param string $name Interface name
     * @param bool $sudo use sudo command before ifup and ifdown
     * @throws Exception
     */
    public function restart($name, $sudo = false)
    {
        if (!$this->_interfaceParsed)
            throw new Exception("Interface file is not parsed");
        if (!array_key_exists($name, $this->Adaptors))
            throw new Exception("$name does not exist is adaptor list");
        $cmd = ($sudo ? 'sudo ' : '') . "ifdown $name && " . ($sudo ? ' sudo ' : '') . "ifup $name";
        shell_exec($cmd);
    }

    /**
     * generate inteface file and write it (or return it)
     * @param bool $return if true, generated file will be returned.
     * @return bool|int|string
     * @throws Exception
     */
    public function write($return = False)
    {
        if (!$this->_interfaceParsed)
            throw new Exception("Interface file is not parsed");
        if (!@is_writable($this->_interfaceFile) && !$return)
            throw new Exception("Interface file is not writable");
        $knownAddresses = ['address', 'netmask', 'gateway', 'broadcast', 'network'];

        $buffer = [];
        $buffer[] = "#This file is generated by www-data";
        $buffer[] = "#" . date('r');
        $buffer[] = '';
        foreach ($this->Adaptors as $adaptor => $detail) {
            if ($detail->auto) $buffer[] = "auto $adaptor";
            foreach ($detail->allows as $item)
                $buffer[] = "allow-$item $adaptor";
            $buffer[] = "iface $adaptor {$detail->family} {$detail->method}";
            foreach ($knownAddresses as $item)
                if (isset($detail->$item)) $buffer[] = " $item {$detail->$item}";
            foreach ($detail->Unknown as $item)
                $buffer[] = " $item";
            $buffer[] = '';
        }
        $imploded = implode("\n", $buffer);
        if ($return)
            return $imploded;
        return file_put_contents($this->_interfaceFile, $imploded);
    }

    public function write_dhcp($return = False)
    {
        if (!$this->_interfaceParsed)
            throw new Exception("Interface file is not parsed");
        if (!@is_writable($this->_interfaceFile) && !$return)
            throw new Exception("Interface file is not writable");
        $buffer = [];
        $buffer[] = "#This file is generated by www-data";
        $buffer[] = "#" . date('r');
        $buffer[] = '';
        foreach ($this->Adaptors as $adaptor => $detail) {
            if ($detail->auto) $buffer[] = "auto $adaptor";
            foreach ($detail->allows as $item)
                $buffer[] = "allow-$item $adaptor";
            $buffer[] = "iface $adaptor {$detail->family} {$detail->method}";
            foreach ($detail->Unknown as $item)
                $buffer[] = " $item";
            $buffer[] = '';
        }
        $imploded = implode("\n", $buffer);
        if ($return)
            return $imploded;
        return file_put_contents($this->_interfaceFile, $imploded);
    }

    /**
     * add a new adaptor to Adaptor property
     * @param Adaptor $Adaptor
     * @throws Exception
     */
    public function add($Adaptor)
    {
        if (!$this->_interfaceParsed)
            throw new Exception("Interface file is not parsed");
        if (array_key_exists($Adaptor->name, $this->Adaptors))
            throw new Exception("{$Adaptor->name} already exist is adaptor list");
        $this->Adaptors[$Adaptor->name] = $Adaptor;
    }

}
