release:
	ncc build --config="release"

install:
	ncc package install --package="build/release/net.nosial.tamerlib.ncc" --skip-dependencies --reinstall -y

uninstall:
	ncc package uninstall -y --package="net.nosial.tamerlib"