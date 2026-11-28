import { HardhatUserConfig, subtask } from "hardhat/config";
import { TASK_COMPILE_SOLIDITY_GET_SOLC_BUILD } from "hardhat/builtin-tasks/task-names";
import "@nomicfoundation/hardhat-toolbox";
import "dotenv/config";

// Use the solc JavaScript compiler from npm instead of downloading a native
// binary, so compiling works offline and behind restrictive networks.
const SOLC_VERSION = "0.8.24";
subtask(TASK_COMPILE_SOLIDITY_GET_SOLC_BUILD).setAction(async (args, _hre, runSuper) => {
  if (args.solcVersion !== SOLC_VERSION) return runSuper();
  const solc = require("solc");
  return {
    compilerPath: require.resolve("solc/soljson.js"),
    isSolcJs: true,
    version: SOLC_VERSION,
    longVersion: solc.version(),
  };
});

// Secrets come from contracts/.env (never committed). See .env.example.
const { SEPOLIA_RPC_URL, DEPLOYER_PRIVATE_KEY, ETHERSCAN_API_KEY } = process.env;

const config: HardhatUserConfig = {
  solidity: {
    version: SOLC_VERSION,
    settings: { optimizer: { enabled: true, runs: 200 } },
  },
  networks: {
    hardhat: {},
    localhost: { url: "http://127.0.0.1:8545" },
    ...(SEPOLIA_RPC_URL && DEPLOYER_PRIVATE_KEY
      ? { sepolia: { url: SEPOLIA_RPC_URL, accounts: [DEPLOYER_PRIVATE_KEY], chainId: 11155111 } }
      : {}),
  },
  etherscan: { apiKey: ETHERSCAN_API_KEY ?? "" },
};

export default config;
