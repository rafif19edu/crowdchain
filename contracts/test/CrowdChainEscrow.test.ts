import { expect } from "chai";
import { ethers } from "hardhat";
import { loadFixture, time } from "@nomicfoundation/hardhat-network-helpers";

// Enum values mirrored from the contract.
const Type = { Project: 0, Weekly: 1 };
const State = { None: 0, Active: 1, Funded: 2, Refunding: 3, Completed: 4, Cancelled: 5 };
const Stage = { Pending: 0, Released: 1, Unfunded: 2, Cancelled: 3 };

const E = (n: string) => ethers.parseEther(n);
const DAY = 24 * 60 * 60;
const PROOF = ethers.keccak256(ethers.toUtf8Bytes("proof"));
const DOCS = ethers.keccak256(ethers.toUtf8Bytes("docs"));

async function deployFixture() {
  const [admin, fundraiser, donorA, donorB, stranger] = await ethers.getSigners();
  const escrow = await (await ethers.getContractFactory("CrowdChainEscrow")).deploy(admin.address);
  return { escrow, admin, fundraiser, donorA, donorB, stranger };
}

/** Project id 1: goal 10 ETH, milestones 3 / 4 / 3, deadline in 30 days. */
async function projectFixture() {
  const f = await deployFixture();
  const deadline = (await time.latest()) + 30 * DAY;
  await f.escrow.createCampaign(1, f.fundraiser.address, Type.Project, deadline, [E("3"), E("4"), E("3")], [], DOCS);
  return { ...f, deadline };
}

/** Weekly id 2: 4 weeks x 0.1 ETH, first release in 7 days. */
async function weeklyFixture() {
  const f = await deployFixture();
  const now = await time.latest();
  const times = [1, 2, 3, 4].map((w) => now + w * 7 * DAY);
  await f.escrow.createCampaign(2, f.fundraiser.address, Type.Weekly, times[3], Array(4).fill(E("0.1")), times, DOCS);
  return { ...f, times };
}

describe("CrowdChainEscrow", () => {
  describe("deployment", () => {
    it("sets the admin as owner", async () => {
      const { escrow, admin } = await loadFixture(deployFixture);
      expect(await escrow.owner()).to.equal(admin.address);
    });
  });

  describe("createCampaign", () => {
    it("creates a project and emits CampaignCreated", async () => {
      const { escrow, fundraiser } = await loadFixture(deployFixture);
      const deadline = (await time.latest()) + 10 * DAY;
      await expect(escrow.createCampaign(7, fundraiser.address, Type.Project, deadline, [E("1"), E("2")], [], DOCS))
        .to.emit(escrow, "CampaignCreated")
        .withArgs(7, fundraiser.address, Type.Project, E("3"), deadline, DOCS);
      const c = await escrow.getCampaign(7);
      expect(c.state).to.equal(State.Active);
      expect(c.goal).to.equal(E("3"));
      expect(c.stageCount).to.equal(2);
    });

    it("creates a weekly campaign with all stages fundable", async () => {
      const { escrow } = await loadFixture(weeklyFixture);
      const c = await escrow.getCampaign(2);
      expect(c.goal).to.equal(E("0.4"));
      expect(c.fundableStages).to.equal(4);
    });

    it("rejects non-owner, duplicate id, zero fundraiser and bad stage counts", async () => {
      const { escrow, fundraiser, stranger } = await loadFixture(projectFixture);
      const deadline = (await time.latest()) + DAY;
      await expect(
        escrow.connect(stranger).createCampaign(9, fundraiser.address, Type.Project, deadline, [E("1")], [], DOCS)
      ).to.be.revertedWithCustomError(escrow, "OwnableUnauthorizedAccount");
      await expect(
        escrow.createCampaign(1, fundraiser.address, Type.Project, deadline, [E("1")], [], DOCS)
      ).to.be.revertedWithCustomError(escrow, "CampaignExists");
      await expect(
        escrow.createCampaign(9, ethers.ZeroAddress, Type.Project, deadline, [E("1")], [], DOCS)
      ).to.be.revertedWithCustomError(escrow, "InvalidFundraiser");
      await expect(
        escrow.createCampaign(9, fundraiser.address, Type.Project, deadline, Array(6).fill(E("1")), [], DOCS)
      ).to.be.revertedWithCustomError(escrow, "InvalidStages");
      await expect(
        escrow.createCampaign(9, fundraiser.address, Type.Project, deadline, [E("1"), 0n], [], DOCS)
      ).to.be.revertedWithCustomError(escrow, "InvalidStages");
    });

    it("rejects a project deadline in the past", async () => {
      const { escrow, fundraiser } = await loadFixture(deployFixture);
      const past = (await time.latest()) - 1;
      await expect(
        escrow.createCampaign(9, fundraiser.address, Type.Project, past, [E("1")], [], DOCS)
      ).to.be.revertedWithCustomError(escrow, "InvalidDeadline");
    });

    it("rejects weekly campaigns with bad schedules or unequal amounts", async () => {
      const { escrow, fundraiser } = await loadFixture(deployFixture);
      const now = await time.latest();
      const t = [1, 2, 3, 4].map((w) => now + w * DAY);
      const amt = Array(4).fill(E("0.1"));
      await expect(escrow.createCampaign(9, fundraiser.address, Type.Weekly, t[2], amt.slice(0, 3), t.slice(0, 3), DOCS))
        .to.be.revertedWithCustomError(escrow, "InvalidStages");
      await expect(escrow.createCampaign(9, fundraiser.address, Type.Weekly, t[3], amt, [t[0], t[0], t[2], t[3]], DOCS))
        .to.be.revertedWithCustomError(escrow, "InvalidSchedule");
      await expect(escrow.createCampaign(9, fundraiser.address, Type.Weekly, t[3], [E("0.1"), E("0.2"), E("0.1"), E("0.1")], t, DOCS))
        .to.be.revertedWithCustomError(escrow, "InvalidStages");
      await expect(escrow.createCampaign(9, fundraiser.address, Type.Weekly, t[2], amt, t, DOCS))
        .to.be.revertedWithCustomError(escrow, "InvalidDeadline");
    });
  });

  describe("donate", () => {
    it("records the donation and emits DonationReceived", async () => {
      const { escrow, donorA } = await loadFixture(projectFixture);
      await expect(escrow.connect(donorA).donate(1, { value: E("2") }))
        .to.emit(escrow, "DonationReceived")
        .withArgs(1, donorA.address, E("2"));
      expect(await escrow.contributionOf(1, donorA.address)).to.equal(E("2"));
      expect(await escrow.escrowBalance(1)).to.equal(E("2"));
    });

    it("marks a project Funded when the goal is reached exactly", async () => {
      const { escrow, donorA } = await loadFixture(projectFixture);
      await expect(escrow.connect(donorA).donate(1, { value: E("10") })).to.emit(escrow, "CampaignFunded").withArgs(1);
      const c = await escrow.getCampaign(1);
      expect(c.state).to.equal(State.Funded);
      expect(c.fundableStages).to.equal(3);
    });

    it("rejects zero, over-goal, late, unknown-campaign and paused donations", async () => {
      const { escrow, donorA, deadline } = await loadFixture(projectFixture);
      await expect(escrow.connect(donorA).donate(1, { value: 0 })).to.be.revertedWithCustomError(escrow, "ZeroAmount");
      await expect(escrow.connect(donorA).donate(1, { value: E("10.01") })).to.be.revertedWithCustomError(escrow, "GoalExceeded");
      await expect(escrow.connect(donorA).donate(99, { value: E("1") })).to.be.revertedWithCustomError(escrow, "CampaignNotFound");
      await escrow.pause();
      await expect(escrow.connect(donorA).donate(1, { value: E("1") })).to.be.revertedWithCustomError(escrow, "EnforcedPause");
      await escrow.unpause();
      await time.increaseTo(deadline);
      await expect(escrow.connect(donorA).donate(1, { value: E("1") })).to.be.revertedWithCustomError(escrow, "DeadlinePassed");
    });

    it("rejects donations to a funded project", async () => {
      const { escrow, donorA, donorB } = await loadFixture(projectFixture);
      await escrow.connect(donorA).donate(1, { value: E("10") });
      await expect(escrow.connect(donorB).donate(1, { value: E("1") })).to.be.revertedWithCustomError(escrow, "InvalidState");
    });
  });

  describe("extendDeadline", () => {
    it("extends once by 30 days", async () => {
      const { escrow, deadline } = await loadFixture(projectFixture);
      await expect(escrow.extendDeadline(1)).to.emit(escrow, "DeadlineExtended").withArgs(1, deadline + 30 * DAY);
      await expect(escrow.extendDeadline(1)).to.be.revertedWithCustomError(escrow, "AlreadyExtended");
    });

    it("rejects weekly campaigns, passed deadlines, reached goals and non-owners", async () => {
      const w = await loadFixture(weeklyFixture);
      await expect(w.escrow.extendDeadline(2)).to.be.revertedWithCustomError(w.escrow, "InvalidState");

      const p = await loadFixture(projectFixture);
      await expect(p.escrow.connect(p.stranger).extendDeadline(1)).to.be.revertedWithCustomError(p.escrow, "OwnableUnauthorizedAccount");
      await time.increaseTo(p.deadline);
      await expect(p.escrow.extendDeadline(1)).to.be.revertedWithCustomError(p.escrow, "DeadlinePassed");
    });

    it("rejects extension once the goal is reached", async () => {
      const { escrow, donorA } = await loadFixture(projectFixture);
      await escrow.connect(donorA).donate(1, { value: E("10") });
      await expect(escrow.extendDeadline(1)).to.be.revertedWithCustomError(escrow, "InvalidState");
    });
  });

  describe("finalize (rule matrix, design doc §5)", () => {
    async function raisedThenFinalize(amount: string) {
      const f = await loadFixture(projectFixture);
      await f.escrow.connect(f.donorA).donate(1, { value: E(amount) });
      await time.increaseTo(f.deadline);
      const tx = f.escrow.connect(f.stranger).finalize(1);
      return { ...f, tx };
    }

    it("raised 8 of 10 → Funded, 2 fundable stages, 1 ETH refund pool", async () => {
      const { escrow, tx } = await raisedThenFinalize("8");
      await expect(tx).to.emit(escrow, "CampaignFinalized").withArgs(1, State.Funded, 2, E("1"));
      const [, , states] = await escrow.getStages(1);
      expect(states.map(Number)).to.deep.equal([Stage.Pending, Stage.Pending, Stage.Unfunded]);
    });

    it("raised 5 → Funded, only M1 fundable, 2 ETH refund pool", async () => {
      const { escrow, tx } = await raisedThenFinalize("5");
      await expect(tx).to.emit(escrow, "CampaignFinalized").withArgs(1, State.Funded, 1, E("2"));
    });

    it("raised 2 (< M1) → Refunding with full refund", async () => {
      const { escrow, tx, donorA } = await raisedThenFinalize("2");
      await expect(tx).to.emit(escrow, "CampaignFinalized").withArgs(1, State.Refunding, 0, E("2"));
      expect(await escrow.refundableOf(1, donorA.address)).to.equal(E("2"));
    });

    it("rejects finalize before the deadline or twice", async () => {
      const { escrow, deadline } = await loadFixture(projectFixture);
      await expect(escrow.finalize(1)).to.be.revertedWithCustomError(escrow, "DeadlineNotReached");
      await time.increaseTo(deadline);
      await escrow.finalize(1);
      await expect(escrow.finalize(1)).to.be.revertedWithCustomError(escrow, "InvalidState");
    });
  });

  describe("project releases and proofs", () => {
    it("runs the full happy path to Completed", async () => {
      const { escrow, fundraiser, donorA } = await loadFixture(projectFixture);
      await escrow.connect(donorA).donate(1, { value: E("10") });

      for (const [i, amount] of [E("3"), E("4"), E("3")].entries()) {
        await expect(escrow.releaseStage(1, i)).to.changeEtherBalances([fundraiser, escrow], [amount, -amount]);
        await expect(escrow.releaseStage(1, i + 1 < 3 ? i + 1 : i)).to.be.reverted; // proof outstanding / wrong stage
        await expect(escrow.recordProof(1, i, PROOF)).to.emit(escrow, "ProofRecorded").withArgs(1, i, PROOF);
      }
      const c = await escrow.getCampaign(1);
      expect(c.state).to.equal(State.Completed);
      expect(c.released).to.equal(E("10"));
      expect(c.refundPool).to.equal(0);
    });

    it("blocks the next release until the proof is recorded", async () => {
      const { escrow, donorA } = await loadFixture(projectFixture);
      await escrow.connect(donorA).donate(1, { value: E("10") });
      await escrow.releaseStage(1, 0);
      await expect(escrow.releaseStage(1, 1)).to.be.revertedWithCustomError(escrow, "ProofOutstanding");
    });

    it("rejects releasing out of order, before funding, or by a non-owner", async () => {
      const { escrow, donorA, stranger } = await loadFixture(projectFixture);
      await escrow.connect(donorA).donate(1, { value: E("5") });
      await expect(escrow.releaseStage(1, 0)).to.be.revertedWithCustomError(escrow, "InvalidState");
      await escrow.connect(donorA).donate(1, { value: E("5") });
      await expect(escrow.releaseStage(1, 1)).to.be.revertedWithCustomError(escrow, "WrongStage");
      await expect(escrow.connect(stranger).releaseStage(1, 0)).to.be.revertedWithCustomError(escrow, "OwnableUnauthorizedAccount");
    });

    it("auto-finalizes on release after the deadline and refuses unfundable stages", async () => {
      const { escrow, donorA, deadline } = await loadFixture(projectFixture);
      await escrow.connect(donorA).donate(1, { value: E("5") });
      await time.increaseTo(deadline);
      await expect(escrow.releaseStage(1, 0)).to.emit(escrow, "CampaignFinalized");
      await escrow.recordProof(1, 0, PROOF);
      const c = await escrow.getCampaign(1);
      expect(c.state).to.equal(State.Completed); // only M1 was fundable
      expect(c.refundPool).to.equal(E("2"));
    });

    it("rejects invalid proofs", async () => {
      const { escrow, donorA } = await loadFixture(projectFixture);
      await escrow.connect(donorA).donate(1, { value: E("10") });
      await expect(escrow.recordProof(1, 0, PROOF)).to.be.revertedWithCustomError(escrow, "InvalidState");
      await escrow.releaseStage(1, 0);
      await expect(escrow.recordProof(1, 0, ethers.ZeroHash)).to.be.revertedWithCustomError(escrow, "InvalidProof");
      await escrow.recordProof(1, 0, PROOF);
      await expect(escrow.recordProof(1, 0, PROOF)).to.be.revertedWithCustomError(escrow, "ProofAlreadyRecorded");
    });
  });

  describe("weekly releases", () => {
    it("releases on schedule, skips an underfunded week and refunds the leftover", async () => {
      const { escrow, fundraiser, donorA, donorB, times } = await loadFixture(weeklyFixture);
      await escrow.connect(donorA).donate(2, { value: E("0.25") });

      await expect(escrow.releaseStage(2, 0)).to.be.revertedWithCustomError(escrow, "StageNotDue");
      await time.increaseTo(times[0]);
      await expect(escrow.releaseStage(2, 0)).to.changeEtherBalance(fundraiser, E("0.1"));
      await escrow.recordProof(2, 0, PROOF);

      await time.increaseTo(times[1]);
      await escrow.releaseStage(2, 1);
      await escrow.recordProof(2, 1, PROOF);

      await time.increaseTo(times[2]); // escrow 0.05 < 0.1
      await expect(escrow.releaseStage(2, 2)).to.be.revertedWithCustomError(escrow, "InsufficientEscrow");
      await expect(escrow.markUnfunded(2, 2)).to.emit(escrow, "StageUnfunded").withArgs(2, 2);

      await escrow.connect(donorB).donate(2, { value: E("0.07") }); // escrow 0.12
      await time.increaseTo(times[3]);
      await expect(escrow.connect(donorB).donate(2, { value: E("0.01") })).to.be.revertedWithCustomError(escrow, "DeadlinePassed");
      await escrow.releaseStage(2, 3);
      await expect(escrow.recordProof(2, 3, PROOF)).to.emit(escrow, "CampaignCompleted").withArgs(2, E("0.02"));

      // 0.02 leftover split pro-rata: A gave 0.25 of 0.32, B gave 0.07 of 0.32.
      const owedA = (E("0.25") * E("0.02")) / E("0.32");
      await expect(escrow.connect(donorA).claimRefund(2)).to.changeEtherBalance(donorA, owedA);
    });

    it("refuses markUnfunded when escrow is sufficient, early, out of order, or on projects", async () => {
      const { escrow, donorA, times } = await loadFixture(weeklyFixture);
      await escrow.connect(donorA).donate(2, { value: E("0.2") });
      await expect(escrow.markUnfunded(2, 0)).to.be.revertedWithCustomError(escrow, "StageNotDue");
      await time.increaseTo(times[0]);
      await expect(escrow.markUnfunded(2, 1)).to.be.revertedWithCustomError(escrow, "WrongStage");
      await expect(escrow.markUnfunded(2, 0)).to.be.revertedWithCustomError(escrow, "EscrowSufficient");

      const p = await loadFixture(projectFixture);
      await expect(p.escrow.markUnfunded(1, 0)).to.be.revertedWithCustomError(p.escrow, "InvalidState");
    });

    it("completes when the last week is marked unfunded", async () => {
      const { escrow, times } = await loadFixture(weeklyFixture);
      for (let i = 0; i < 4; i++) {
        await time.increaseTo(times[i]);
        await escrow.markUnfunded(2, i);
      }
      expect((await escrow.getCampaign(2)).state).to.equal(State.Completed);
    });
  });

  describe("cancel and refunds", () => {
    it("partial refund first, then cancel enlarges the pool and the donor claims the difference", async () => {
      const { escrow, donorA, donorB, deadline } = await loadFixture(projectFixture);
      await escrow.connect(donorA).donate(1, { value: E("6") });
      await escrow.connect(donorB).donate(1, { value: E("2") }); // raised 8
      await time.increaseTo(deadline);
      await escrow.finalize(1); // M1+M2 fundable (7), pool 1

      const firstA = (E("6") * E("1")) / E("8"); // 0.75
      await expect(escrow.connect(donorA).claimRefund(1)).to.changeEtherBalance(donorA, firstA);

      await escrow.releaseStage(1, 0); // 3 released
      await expect(escrow.cancelCampaign(1)).to.emit(escrow, "CampaignCancelled").withArgs(1, E("5"));

      const totalA = (E("6") * E("5")) / E("8"); // 3.75
      await expect(escrow.connect(donorA).claimRefund(1)).to.changeEtherBalance(donorA, totalA - firstA);
      await expect(escrow.connect(donorA).claimRefund(1)).to.be.revertedWithCustomError(escrow, "NothingToRefund");
      await expect(escrow.connect(donorB).claimRefund(1)).to.changeEtherBalance(donorB, (E("2") * E("5")) / E("8"));
    });

    it("full refund when finalize lands below M1, auto-finalized by claimRefund", async () => {
      const { escrow, donorA, deadline } = await loadFixture(projectFixture);
      await escrow.connect(donorA).donate(1, { value: E("2") });
      await time.increaseTo(deadline);
      await expect(escrow.connect(donorA).claimRefund(1)).to.changeEtherBalance(donorA, E("2"));
      expect((await escrow.getCampaign(1)).state).to.equal(State.Refunding);
    });

    it("rejects cancel on finished campaigns, refunds with nothing owed, and stranger cancels", async () => {
      const { escrow, donorA, stranger } = await loadFixture(projectFixture);
      await expect(escrow.connect(donorA).claimRefund(1)).to.be.revertedWithCustomError(escrow, "NothingToRefund");
      await expect(escrow.connect(stranger).cancelCampaign(1)).to.be.revertedWithCustomError(escrow, "OwnableUnauthorizedAccount");
      await escrow.cancelCampaign(1);
      await expect(escrow.cancelCampaign(1)).to.be.revertedWithCustomError(escrow, "InvalidState");
      const [, , states] = await escrow.getStages(1);
      expect(states.map(Number)).to.deep.equal([Stage.Cancelled, Stage.Cancelled, Stage.Cancelled]);
    });

    it("blocks refunds while paused", async () => {
      const { escrow, donorA } = await loadFixture(projectFixture);
      await escrow.connect(donorA).donate(1, { value: E("1") });
      await escrow.cancelCampaign(1);
      await escrow.pause();
      await expect(escrow.connect(donorA).claimRefund(1)).to.be.revertedWithCustomError(escrow, "EnforcedPause");
      await escrow.unpause();
      await expect(escrow.connect(donorA).claimRefund(1)).to.changeEtherBalance(donorA, E("1"));
    });
  });

  describe("security", () => {
    it("defeats a reentrancy attack on claimRefund", async () => {
      const { escrow } = await loadFixture(projectFixture);
      const attacker = await (await ethers.getContractFactory("ReentrancyAttacker")).deploy(await escrow.getAddress());
      await attacker.donate(1, { value: E("1") });
      await escrow.cancelCampaign(1);

      // The re-entrant call reverts inside receive(), so the outer transfer fails as a whole.
      await expect(attacker.attack()).to.be.revertedWithCustomError(escrow, "TransferFailed");
      expect(await escrow.refundableOf(1, await attacker.getAddress())).to.equal(E("1"));
      expect(await ethers.provider.getBalance(await escrow.getAddress())).to.equal(E("1"));
    });

    it("only the owner can pause", async () => {
      const { escrow, stranger } = await loadFixture(deployFixture);
      await expect(escrow.connect(stranger).pause()).to.be.revertedWithCustomError(escrow, "OwnableUnauthorizedAccount");
    });

    it("accounting ignores ETH forced into the contract", async () => {
      const { escrow, donorA } = await loadFixture(projectFixture);
      await escrow.connect(donorA).donate(1, { value: E("1") });
      expect(await escrow.escrowBalance(1)).to.equal(E("1"));
      // There is no receive(); plain transfers revert, and balances come from counters only.
      await expect(donorA.sendTransaction({ to: await escrow.getAddress(), value: E("1") })).to.be.reverted;
    });
  });
});
