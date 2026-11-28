// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

interface IEscrow {
    function donate(uint256 id) external payable;
    function claimRefund(uint256 id) external;
}

/// @dev Test-only contract. Donates, then tries to call claimRefund again
///      from inside receive() while the first refund is being paid.
contract ReentrancyAttacker {
    IEscrow public immutable escrow;
    uint256 public targetId;
    uint256 public reentryAttempts;

    constructor(address escrowAddress) {
        escrow = IEscrow(escrowAddress);
    }

    function donate(uint256 id) external payable {
        targetId = id;
        escrow.donate{value: msg.value}(id);
    }

    function attack() external {
        escrow.claimRefund(targetId);
    }

    receive() external payable {
        reentryAttempts++;
        escrow.claimRefund(targetId);
    }
}
