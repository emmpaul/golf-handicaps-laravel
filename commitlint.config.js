// Le scope est facultatif ; s'il est présent, il appartient à cette liste fermée.
// Chaque changement qui introduit un domaine ajoute ses scopes ici, dans le même lot que son code.
export default {
    extends: ['@commitlint/config-conventional'],
    rules: {
        'scope-enum': [
            2,
            'always',
            ['openspec', 'deps', 'ci', 'docker', 'make', 'db', 'auth', 'ui', 'adr', 'docs'],
        ],
    },
};
