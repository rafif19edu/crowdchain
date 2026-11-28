import { ethers } from "hardhat";

// Usage: npm run deploy:local  |  npm run deploy:sepolia
// ADMIN_WALLET_ADDRESS (optional) sets the contract owner; defaults to the deployer.
async function main() {
  const [deployer] = await ethers.getSigners();
  const admin = process.env.ADMIN_WALLET_ADDRESS || deployer.address;

  const factory = await ethers.getContractFactory("CrowdChainEscrow");
  const escrow = await factory.deploy(admin);
  const receipt = await escrow.deploymentTransaction()?.wait();
  await escrow.waitForDeployment();

  console.log("CrowdChainEscrow deployed");
  console.log("  CONTRACT_ADDRESS      =", await escrow.getAddress());
  console.log("  CONTRACT_DEPLOY_BLOCK =", receipt?.blockNumber);
  console.log("  owner (admin wallet)  =", admin);
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
