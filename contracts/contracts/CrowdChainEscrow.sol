// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import {Ownable} from "@openzeppelin/contracts/access/Ownable.sol";
import {ReentrancyGuard} from "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import {Pausable} from "@openzeppelin/contracts/utils/Pausable.sol";

/// @title CrowdChainEscrow
/// @notice Holds donations for CrowdChain campaigns and releases them stage by
///         stage. A stage can only be released after the proof for the
///         previous stage has been recorded. Shortfalls and cancellations are
///         refunded pro-rata, and donors pull their own refunds.
/// @dev Design: docs/12-smart-contract-design.md. Campaign ids are the
///      database ids of the CrowdChain backend (decision T3).
contract CrowdChainEscrow is Ownable, ReentrancyGuard, Pausable {
    // ------------------------------------------------------------------
    // Types
    // ------------------------------------------------------------------

    enum CampaignType {
        Project,
        Weekly
    }

    enum CampaignState {
        None,
        Active,
        Funded,
        Refunding,
        Completed,
        Cancelled
    }

    enum StageState {
        Pending,
        Released,
        Unfunded,
        Cancelled
    }

    struct Campaign {
        address fundraiser;
        CampaignType ctype;
        CampaignState state;
        uint64 deadline;
        bool extended;
        bool awaitingProof;
        uint8 stageCount;
        uint8 nextStage;
        uint8 fundableStages;
        uint256 goal;
        uint256 raised;
        uint256 released;
        uint256 refundPool;
        bytes32 docsHash;
    }

    // ------------------------------------------------------------------
    // Constants
    // ------------------------------------------------------------------

    uint64 public constant EXTENSION_PERIOD = 30 days;
    uint8 public constant MAX_PROJECT_STAGES = 5;
    uint8 public constant MIN_WEEKLY_STAGES = 4;
    uint8 public constant MAX_WEEKLY_STAGES = 12;

    // ------------------------------------------------------------------
    // Storage
    // ------------------------------------------------------------------

    mapping(uint256 => Campaign) private _campaigns;
    mapping(uint256 => uint256[]) private _stageAmount;
    mapping(uint256 => uint64[]) private _stageTime;
    mapping(uint256 => mapping(uint8 => StageState)) private _stageState;
    mapping(uint256 => mapping(uint8 => bytes32)) private _proofHash;
    mapping(uint256 => mapping(address => uint256)) private _contributed;
    mapping(uint256 => mapping(address => uint256)) private _refunded;

    // ------------------------------------------------------------------
    // Events
    // ------------------------------------------------------------------

    event CampaignCreated(
        uint256 indexed id,
        address indexed fundraiser,
        CampaignType ctype,
        uint256 goal,
        uint64 deadline,
        bytes32 docsHash
    );
    event DonationReceived(uint256 indexed id, address indexed donor, uint256 amount);
    event CampaignFunded(uint256 indexed id);
    event DeadlineExtended(uint256 indexed id, uint64 newDeadline);
    event CampaignFinalized(uint256 indexed id, CampaignState state, uint8 fundableStages, uint256 refundPool);
    event StageReleased(uint256 indexed id, uint8 indexed stage, uint256 amount, address fundraiser);
    event StageUnfunded(uint256 indexed id, uint8 indexed stage);
    event ProofRecorded(uint256 indexed id, uint8 indexed stage, bytes32 proofHash);
    event CampaignCancelled(uint256 indexed id, uint256 refundPool);
    event CampaignCompleted(uint256 indexed id, uint256 refundPool);
    event RefundClaimed(uint256 indexed id, address indexed donor, uint256 amount);

    // ------------------------------------------------------------------
    // Errors
    // ------------------------------------------------------------------

    error CampaignExists();
    error CampaignNotFound();
    error InvalidFundraiser();
    error InvalidStages();
    error InvalidSchedule();
    error InvalidDeadline();
    error InvalidState();
    error DeadlinePassed();
    error DeadlineNotReached();
    error ZeroAmount();
    error GoalExceeded();
    error AlreadyExtended();
    error GoalAlreadyReached();
    error WrongStage();
    error StageNotDue();
    error StageNotFundable();
    error ProofOutstanding();
    error InsufficientEscrow();
    error EscrowSufficient();
    error ProofAlreadyRecorded();
    error InvalidProof();
    error NothingToRefund();
    error TransferFailed();

    // ------------------------------------------------------------------
    // Constructor
    // ------------------------------------------------------------------

    /// @param admin The platform admin wallet (contract owner).
    constructor(address admin) Ownable(admin) {}

    // ------------------------------------------------------------------
    // Admin functions
    // ------------------------------------------------------------------

    /// @notice Creates a campaign after the admin approves it off-chain.
    /// @param id        Backend campaign id (must be unused).
    /// @param fundraiser Receiving wallet of the organisation.
    /// @param ctype     Project or Weekly.
    /// @param deadline  Project: donation deadline. Weekly: must equal the last release time.
    /// @param amounts   Wei per stage (project 1-5; weekly 4-12, all equal).
    /// @param times     Weekly release times (strictly increasing); empty for projects.
    /// @param docsHash  Hash of the approved campaign documents.
    function createCampaign(
        uint256 id,
        address fundraiser,
        CampaignType ctype,
        uint64 deadline,
        uint256[] calldata amounts,
        uint64[] calldata times,
        bytes32 docsHash
    ) external onlyOwner {
        if (_campaigns[id].state != CampaignState.None) revert CampaignExists();
        if (fundraiser == address(0)) revert InvalidFundraiser();

        uint256 count = amounts.length;
        uint256 goal;

        if (ctype == CampaignType.Project) {
            if (count == 0 || count > MAX_PROJECT_STAGES) revert InvalidStages();
            if (times.length != 0) revert InvalidSchedule();
            if (deadline <= block.timestamp) revert InvalidDeadline();
        } else {
            if (count < MIN_WEEKLY_STAGES || count > MAX_WEEKLY_STAGES) revert InvalidStages();
            if (times.length != count) revert InvalidSchedule();
            if (times[0] <= block.timestamp) revert InvalidSchedule();
            for (uint256 i = 1; i < count; i++) {
                if (times[i] <= times[i - 1]) revert InvalidSchedule();
                if (amounts[i] != amounts[0]) revert InvalidStages();
            }
            if (deadline != times[count - 1]) revert InvalidDeadline();
        }

        for (uint256 i = 0; i < count; i++) {
            if (amounts[i] == 0) revert InvalidStages();
            goal += amounts[i];
        }

        Campaign storage c = _campaigns[id];
        c.fundraiser = fundraiser;
        c.ctype = ctype;
        c.state = CampaignState.Active;
        c.deadline = deadline;
        c.stageCount = uint8(count);
        c.fundableStages = ctype == CampaignType.Weekly ? uint8(count) : 0;
        c.goal = goal;
        c.docsHash = docsHash;

        _stageAmount[id] = amounts;
        _stageTime[id] = times;

        emit CampaignCreated(id, fundraiser, ctype, goal, deadline, docsHash);
    }

    /// @notice Extends a project's deadline by 30 days, once, before it passes.
    function extendDeadline(uint256 id) external onlyOwner {
        Campaign storage c = _existing(id);
        if (c.ctype != CampaignType.Project || c.state != CampaignState.Active) revert InvalidState();
        if (c.extended) revert AlreadyExtended();
        if (block.timestamp >= c.deadline) revert DeadlinePassed();
        if (c.raised >= c.goal) revert GoalAlreadyReached();

        c.deadline += EXTENSION_PERIOD;
        c.extended = true;
        emit DeadlineExtended(id, c.deadline);
    }

    /// @notice Pays the next stage to the fundraiser wallet.
    function releaseStage(uint256 id, uint8 stage) external onlyOwner nonReentrant whenNotPaused {
        Campaign storage c = _existing(id);
        _finalizeIfDue(id, c);

        if (stage != c.nextStage) revert WrongStage();
        if (c.awaitingProof) revert ProofOutstanding();
        if (_stageState[id][stage] != StageState.Pending) revert InvalidState();

        if (c.ctype == CampaignType.Project) {
            if (c.state != CampaignState.Funded) revert InvalidState();
            if (stage >= c.fundableStages) revert StageNotFundable();
        } else {
            if (c.state != CampaignState.Active) revert InvalidState();
            if (block.timestamp < _stageTime[id][stage]) revert StageNotDue();
        }

        uint256 amount = _stageAmount[id][stage];
        if (_escrowBalance(c) < amount) revert InsufficientEscrow();

        // Effects before the external call (checks-effects-interactions).
        c.released += amount;
        _stageState[id][stage] = StageState.Released;
        c.nextStage = stage + 1;
        c.awaitingProof = true;

        _send(c.fundraiser, amount);
        emit StageReleased(id, stage, amount, c.fundraiser);
    }

    /// @notice Skips a weekly stage whose release day arrived with too little in escrow.
    function markUnfunded(uint256 id, uint8 stage) external onlyOwner {
        Campaign storage c = _existing(id);
        if (c.ctype != CampaignType.Weekly || c.state != CampaignState.Active) revert InvalidState();
        if (stage != c.nextStage) revert WrongStage();
        if (block.timestamp < _stageTime[id][stage]) revert StageNotDue();
        if (_escrowBalance(c) >= _stageAmount[id][stage]) revert EscrowSufficient();

        _stageState[id][stage] = StageState.Unfunded;
        c.nextStage = stage + 1;
        emit StageUnfunded(id, stage);

        _completeIfDone(id, c);
    }

    /// @notice Records the hash of the approved proof of spending for a released stage.
    function recordProof(uint256 id, uint8 stage, bytes32 proofHash) external onlyOwner {
        Campaign storage c = _existing(id);
        if (stage >= c.stageCount || _stageState[id][stage] != StageState.Released) revert InvalidState();
        if (_proofHash[id][stage] != bytes32(0)) revert ProofAlreadyRecorded();
        if (proofHash == bytes32(0)) revert InvalidProof();

        _proofHash[id][stage] = proofHash;
        c.awaitingProof = false;
        emit ProofRecorded(id, stage, proofHash);

        _completeIfDone(id, c);
    }

    /// @notice Cancels a campaign; everything not yet released becomes refundable.
    function cancelCampaign(uint256 id) external onlyOwner {
        Campaign storage c = _existing(id);
        if (c.state != CampaignState.Active && c.state != CampaignState.Funded) revert InvalidState();

        c.refundPool = c.raised - c.released;
        for (uint8 i = c.nextStage; i < c.stageCount; i++) {
            if (_stageState[id][i] == StageState.Pending) {
                _stageState[id][i] = StageState.Cancelled;
            }
        }
        c.state = CampaignState.Cancelled;
        emit CampaignCancelled(id, c.refundPool);
    }

    function pause() external onlyOwner {
        _pause();
    }

    function unpause() external onlyOwner {
        _unpause();
    }

    // ------------------------------------------------------------------
    // Public functions
    // ------------------------------------------------------------------

    /// @notice Donates ETH to a campaign's escrow.
    function donate(uint256 id) external payable whenNotPaused {
        Campaign storage c = _existing(id);
        if (c.state != CampaignState.Active) revert InvalidState();
        if (block.timestamp >= c.deadline) revert DeadlinePassed();
        if (msg.value == 0) revert ZeroAmount();
        if (c.raised + msg.value > c.goal) revert GoalExceeded();

        _contributed[id][msg.sender] += msg.value;
        c.raised += msg.value;
        emit DonationReceived(id, msg.sender, msg.value);

        if (c.ctype == CampaignType.Project && c.raised == c.goal) {
            c.state = CampaignState.Funded;
            c.fundableStages = c.stageCount;
            emit CampaignFunded(id);
        }
    }

    /// @notice Settles a project after its deadline. Anyone may call it.
    function finalize(uint256 id) external {
        Campaign storage c = _existing(id);
        if (c.ctype != CampaignType.Project || c.state != CampaignState.Active) revert InvalidState();
        if (block.timestamp < c.deadline) revert DeadlineNotReached();
        _finalize(id, c);
    }

    /// @notice Pays the caller their outstanding pro-rata share of the refund pool.
    function claimRefund(uint256 id) external nonReentrant whenNotPaused {
        Campaign storage c = _existing(id);
        _finalizeIfDue(id, c);

        uint256 owed = _refundableOf(id, c, msg.sender);
        if (owed == 0) revert NothingToRefund();

        _refunded[id][msg.sender] += owed;
        _send(msg.sender, owed);
        emit RefundClaimed(id, msg.sender, owed);
    }

    // ------------------------------------------------------------------
    // Views
    // ------------------------------------------------------------------

    function getCampaign(uint256 id) external view returns (Campaign memory) {
        return _campaigns[id];
    }

    function getStages(uint256 id)
        external
        view
        returns (uint256[] memory amounts, uint64[] memory times, StageState[] memory states, bytes32[] memory proofs)
    {
        uint8 count = _campaigns[id].stageCount;
        amounts = _stageAmount[id];
        times = _stageTime[id];
        states = new StageState[](count);
        proofs = new bytes32[](count);
        for (uint8 i = 0; i < count; i++) {
            states[i] = _stageState[id][i];
            proofs[i] = _proofHash[id][i];
        }
    }

    function escrowBalance(uint256 id) external view returns (uint256) {
        return _escrowBalance(_campaigns[id]);
    }

    function contributionOf(uint256 id, address donor) external view returns (uint256) {
        return _contributed[id][donor];
    }

    /// @notice Refund currently claimable by `donor` (ignores a pending, unexecuted finalize).
    function refundableOf(uint256 id, address donor) external view returns (uint256) {
        return _refundableOf(id, _campaigns[id], donor);
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    function _existing(uint256 id) private view returns (Campaign storage c) {
        c = _campaigns[id];
        if (c.state == CampaignState.None) revert CampaignNotFound();
    }

    function _escrowBalance(Campaign storage c) private view returns (uint256) {
        return c.raised - c.released - c.refundPool;
    }

    function _refundableOf(uint256 id, Campaign storage c, address donor) private view returns (uint256) {
        if (c.raised == 0 || c.refundPool == 0) return 0;
        uint256 share = (_contributed[id][donor] * c.refundPool) / c.raised;
        uint256 paid = _refunded[id][donor];
        return share > paid ? share - paid : 0;
    }

    function _finalizeIfDue(uint256 id, Campaign storage c) private {
        if (c.ctype == CampaignType.Project && c.state == CampaignState.Active && block.timestamp >= c.deadline) {
            _finalize(id, c);
        }
    }

    function _finalize(uint256 id, Campaign storage c) private {
        uint256[] storage amounts = _stageAmount[id];
        uint256 cumulative;
        uint8 k;
        while (k < c.stageCount && cumulative + amounts[k] <= c.raised) {
            cumulative += amounts[k];
            k++;
        }

        for (uint8 i = k; i < c.stageCount; i++) {
            _stageState[id][i] = StageState.Unfunded;
            emit StageUnfunded(id, i);
        }

        c.fundableStages = k;
        c.refundPool = c.raised - cumulative;
        c.state = k == 0 ? CampaignState.Refunding : CampaignState.Funded;
        emit CampaignFinalized(id, c.state, k, c.refundPool);
    }

    function _completeIfDone(uint256 id, Campaign storage c) private {
        if (c.awaitingProof) return;
        if (c.state != CampaignState.Active && c.state != CampaignState.Funded) return;

        uint8 last = c.ctype == CampaignType.Project ? c.fundableStages : c.stageCount;
        if (c.nextStage < last) return;

        c.refundPool = c.raised - c.released;
        c.state = CampaignState.Completed;
        emit CampaignCompleted(id, c.refundPool);
    }

    function _send(address to, uint256 amount) private {
        (bool ok, ) = to.call{value: amount}("");
        if (!ok) revert TransferFailed();
    }
}
