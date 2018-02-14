function f() {
  return '__proto__' in {} ? 'hasProto' : 'noProto';
}
