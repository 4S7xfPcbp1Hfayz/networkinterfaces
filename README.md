# NetworkInterfaces

This is a fork of the [original NetworkInterfaces repository](https://github.com/carp3/networkinterfaces) created by [carp3](https://github.com/carp3).

NetworkInterfaces is a simple PHP library for reading and manipulating the `/etc/network/interfaces` file in Debian-based distributions.

You can find the original repository [here](https://github.com/carp3/networkinterfaces).

## Description

NetworkInterfaces is designed to provide an easy-to-use PHP interface for working with the `/etc/network/interfaces` configuration file on Debian-based Linux distributions. It simplifies the process of reading and manipulating network interface configurations, making it a valuable tool for system administrators and developers working on Debian-based systems.

To use this library, make sure you have the following dependencies installed:

- [ethtool](https://linux.die.net/man/8/ethtool)
- [net-tools](https://github.com/net-tools/net-tools)

### Features

With this forked version of NetworkInterfaces, you can:

- Retrieve the MAC address of an interface
- Get the IP address of an interface
- Get the netmask of an interface
- Get the MTU (Maximum Transmission Unit) of an interface
- Get the speed of an interface
- Get the duplex mode of an interface
- Get the gateway of an interface

## Credits

- [carp3](https://github.com/carp3) - Original project creator
